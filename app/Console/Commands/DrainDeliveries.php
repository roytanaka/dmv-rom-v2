<?php

namespace App\Console\Commands;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\SignUpCancelled;
use App\Models\Delivery;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * The Drain (spec #479, ADR-0024) — the every-minute pass that empties the Delivery queue.
 * It is the only code in the app that sends mail; every send elsewhere writes a Delivery row
 * and leaves it here.
 *
 * A pass takes at most {@see PASS_LIMIT} pending rows, and never enough to push past
 * {@see HOURLY_LIMIT} sends in any rolling hour — the host caps outgoing mail at 500/hour and
 * the department's people need the rest — oldest first. Each row is built into its kind's
 * Mailable, rendered in the recipient's saved locale, sent, and marked sent.
 *
 * Failure classes, retries, expiry, and the status cache keys are the next ticket (#482); this
 * pass only needs a send to succeed or to throw. Only the Notice kind exists so far.
 */
class DrainDeliveries extends Command
{
    protected $signature = 'mail:drain';

    protected $description = 'Send a slice of the pending Delivery queue';

    /** The most rows one pass will send — keeps a slow pass from monopolising the hour. */
    public const PASS_LIMIT = 8;

    /** The rolling-hour ceiling, held under the host's own 500/hour limit. */
    public const HOURLY_LIMIT = 450;

    public function handle(): int
    {
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

        foreach ($due as $delivery) {
            $this->send($delivery);
        }

        return self::SUCCESS;
    }

    /**
     * Send one row in its recipient's saved language and mark it sent. The address is the
     * snapshot on the row, not the Member's current one; the Member is read only for locale.
     */
    private function send(Delivery $delivery): void
    {
        $locale = $delivery->member->preferredLocale() ?? config('app.locale');

        Mail::to($delivery->email)
            ->locale($locale)
            ->send($this->mailableFor($delivery));

        $delivery->update([
            'state' => DeliveryState::Sent,
            'sent_at' => now(),
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
