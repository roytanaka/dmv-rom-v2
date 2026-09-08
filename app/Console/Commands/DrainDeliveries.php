<?php

namespace App\Console\Commands;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\SignUpCancelled;
use App\Models\Delivery;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * The Drain (spec #479, ADR-0024 §4, §10) — the every-minute pass that empties the Delivery
 * queue. It is the only code in the app that sends mail; every send elsewhere writes a Delivery
 * row and leaves it here.
 *
 * A pass takes at most {@see PASS_LIMIT} pending rows, and never enough to push past
 * {@see HOURLY_LIMIT} sends in any rolling hour — the host caps outgoing mail at 500/hour and
 * the department's people need the rest — oldest first. Each row is built into its kind's
 * Mailable, rendered in the recipient's saved locale, sent, and marked sent.
 *
 * Failure comes in two classes (ADR-0024 §4):
 *
 * - **Connection-level** — the host will not connect or authenticate (also how the daily cap
 *   presents over SMTP). The pass stops before its slice, no row changes, the connection error
 *   is recorded and reported (not swallowed), and the next minute tries again. There is no
 *   give-up: outages end and the rolling cap clears.
 * - **Row-level** — one address the host rejects mid-send. Classified by the SMTP reply code
 *   Symfony puts on the exception: a permanent 5xx fails the row at once; a temporary 4xx (or a
 *   connection dropped mid-message, which carries no code) is retried three times — next pass,
 *   then +10 minutes, then +1 hour — before it is failed.
 *
 * The pass writes one status cache key every time it runs ({@see SCHEDULER_LAST_RAN}, the proof
 * the cron is alive) and one only when the connection fails ({@see LAST_CONNECTION_ERROR}); the
 * Mail status page (#492) reads both. "Mail last sent" is not a cache key — it is derived from
 * the newest sent row — so a successful send writes nothing to the cache.
 */
class DrainDeliveries extends Command
{
    protected $signature = 'mail:drain';

    protected $description = 'Send a slice of the pending Delivery queue';

    /** The most rows one pass will send — keeps a slow pass from monopolising the hour. */
    public const PASS_LIMIT = 8;

    /** The rolling-hour ceiling, held under the host's own 500/hour limit. */
    public const HOURLY_LIMIT = 450;

    /** Written every pass — the heartbeat the Mail status page reads to judge the cron alive. */
    public const SCHEDULER_LAST_RAN = 'mail.scheduler_last_ran';

    /** Written only on a connection-level failure — the message and time of the last one. */
    public const LAST_CONNECTION_ERROR = 'mail.last_connection_error';

    /** The temporary-failure retry schedule, in minutes from now, keyed by the attempt number. */
    private const RETRY_DELAYS = [1 => 0, 2 => 10, 3 => 60];

    public function handle(): int
    {
        // The heartbeat first, so a pass that later stops on a dead host still proves the cron
        // ran — the two status warnings are told apart by this key and the connection error.
        Cache::forever(self::SCHEDULER_LAST_RAN, now());

        $this->expirePastDue();

        $sentLastHour = Delivery::query()
            ->where('state', DeliveryState::Sent)
            ->where('sent_at', '>=', now()->subHour())
            ->count();

        $budget = min(self::PASS_LIMIT, self::HOURLY_LIMIT - $sentLastHour);

        if ($budget <= 0) {
            return self::SUCCESS;
        }

        $due = Delivery::query()
            ->with('member')
            ->where('state', DeliveryState::Pending)
            ->where('next_attempt_at', '<=', now())
            // Oldest first, no priority — a Notice queued behind a big send waits its turn.
            ->orderBy('id')
            ->limit($budget)
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        // One connect-and-authenticate for the whole slice. A failure here is connection-level:
        // stop before touching a row, record and report, and let the next minute try again.
        if (! $this->connectionIsUp()) {
            return self::SUCCESS;
        }

        foreach ($due as $delivery) {
            $this->send($delivery);
        }

        return self::SUCCESS;
    }

    /**
     * Mark every pending row whose expiry has passed as expired, so a revived queue does not
     * send a Reminder for a Shift that already started (ADR-0024 §4). Rows without an expiry —
     * Broadcasts, Notices, Direct messages — are never touched; late is still wanted.
     */
    private function expirePastDue(): void
    {
        Delivery::query()
            ->where('state', DeliveryState::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['state' => DeliveryState::Expired]);
    }

    /**
     * Ask the transport to connect and authenticate once. Returns false on a connection-level
     * failure, having recorded the error for the status page and reported it so the developer
     * hears about "cron runs but cannot send" once, grouped, not every minute. Transports with
     * no session to open (the log and array mailers staging uses) are always up.
     */
    private function connectionIsUp(): bool
    {
        $mailer = Mail::mailer();

        if (! $mailer instanceof Mailer) {
            return true;
        }

        $transport = $mailer->getSymfonyTransport();

        if (! $transport instanceof SmtpTransport) {
            return true;
        }

        try {
            $transport->start();
        } catch (TransportExceptionInterface $e) {
            Cache::forever(self::LAST_CONNECTION_ERROR, ['message' => $e->getMessage(), 'at' => now()]);
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Send one row in its recipient's saved language and mark it sent. The address is the
     * snapshot on the row, not the Member's current one; the Member is read only for locale. A
     * row-level rejection is caught and classified rather than allowed to abort the slice.
     */
    private function send(Delivery $delivery): void
    {
        $locale = $delivery->member->preferredLocale() ?? config('app.locale');

        try {
            Mail::to($delivery->email)
                ->locale($locale)
                ->send($this->mailableFor($delivery));
        } catch (TransportExceptionInterface $e) {
            $this->recordFailure($delivery, $e);

            return;
        }

        $delivery->update([
            'state' => DeliveryState::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Classify a row-level failure by its SMTP reply code and update the row. A 5xx is
     * permanent and fails at once; anything else — a 4xx, or the no-code exception a dropped
     * connection throws — is temporary and retried on the {@see RETRY_DELAYS} schedule until the
     * fourth failure fails it. The error message is stored either way.
     */
    private function recordFailure(Delivery $delivery, TransportExceptionInterface $e): void
    {
        $attempts = $delivery->attempts + 1;
        $code = $e->getCode();
        $permanent = $code >= 500 && $code <= 599;

        if ($permanent || ! isset(self::RETRY_DELAYS[$attempts])) {
            $delivery->update([
                'state' => DeliveryState::Failed,
                'attempts' => $attempts,
                'failed_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            return;
        }

        $delivery->update([
            'attempts' => $attempts,
            'next_attempt_at' => now()->addMinutes(self::RETRY_DELAYS[$attempts]),
            'last_error' => $e->getMessage(),
        ]);
    }

    /**
     * Build the Mailable for a row from its kind. A Notice renders from the row's payload
     * snapshot, because the Sign-up it announces is gone by now.
     */
    private function mailableFor(Delivery $delivery): Mailable
    {
        return match ($delivery->kind) {
            DeliveryKind::Notice => SignUpCancelled::fromSnapshot($delivery->payload),
        };
    }
}
