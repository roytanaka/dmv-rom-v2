<?php

namespace App\Http\Controllers;

use App\Console\Commands\DrainDeliveries;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Mail status page (#492, ADR-0024 §10) — super-tier only, so the sole engineer can tell a
 * dead cron from a full queue without opening a shell. It reads the two status cache keys the
 * Drain writes ({@see DrainDeliveries::SCHEDULER_LAST_RAN}, {@see DrainDeliveries::LAST_CONNECTION_ERROR}),
 * derives "mail last sent" from the newest sent Delivery, and counts the pending rows.
 *
 * Two warnings are judged here, not on the client: **Dead** when the scheduler heartbeat is
 * older than ten minutes or was never written (a deploy's cache clear wipes it; the next pass
 * rewrites it within a minute), and **Cannot send** when the last connection error is newer than
 * the last successful send. "Mail last sent" is shown, never judged.
 */
class MailStatusController extends Controller
{
    /** A heartbeat older than this many minutes reads as a dead cron. */
    private const DEAD_AFTER_MINUTES = 10;

    public function __invoke(): Response
    {
        // The gate returns false for everyone; only the super-tier Gate::before short-circuit
        // grants it (ADR-0024 §10). Records authority is not enough.
        Gate::authorize('view-mail-status');

        $schedulerLastRan = Cache::get(DrainDeliveries::SCHEDULER_LAST_RAN);
        $connectionError = Cache::get(DrainDeliveries::LAST_CONNECTION_ERROR);

        $mailLastSent = Delivery::query()
            ->where('state', DeliveryState::Sent)
            ->max('sent_at');
        $mailLastSent = $mailLastSent ? Carbon::parse($mailLastSent) : null;

        $errorAt = isset($connectionError['at']) ? Carbon::parse($connectionError['at']) : null;

        return Inertia::render('MailStatus', [
            'schedulerLastRan' => $schedulerLastRan?->toIso8601String(),
            'mailLastSent' => $mailLastSent?->toIso8601String(),
            'lastConnectionError' => $connectionError ? [
                'message' => $connectionError['message'],
                'at' => $errorAt?->toIso8601String(),
            ] : null,
            'pendingCount' => Delivery::query()->where('state', DeliveryState::Pending)->count(),
            'warnings' => [
                // Dead: the heartbeat is stale or was never written.
                'dead' => $schedulerLastRan === null
                    || $schedulerLastRan->lt(now()->subMinutes(self::DEAD_AFTER_MINUTES)),
                // Cannot send: an error stands newer than the last good send (or there is no
                // good send to stand against).
                'cannotSend' => $errorAt !== null
                    && ($mailLastSent === null || $errorAt->gt($mailLastSent)),
            ],
        ]);
    }
}
