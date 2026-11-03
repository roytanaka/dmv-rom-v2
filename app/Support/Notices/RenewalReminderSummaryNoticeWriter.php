<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\RenewalReminderSummary;
use App\Models\Delivery;
use App\Models\Member;

/**
 * The summary of one daily-pass run of series mail (#942, ADR-0038 §9): one Notice
 * {@see Delivery} per member of the Records Audience, skipping the no-email flag, listing who was
 * mailed in each series. The Legacy app mailed this list to the Records mailbox after each series.
 * The caller writes it only when its run wrote at least one Delivery.
 */
class RenewalReminderSummaryNoticeWriter
{
    /**
     * @param  array<string, list<string>>  $mailed  full names, keyed by series (`active-1` …)
     */
    public function write(array $mailed, int $seriesYear): void
    {
        $payload = RenewalReminderSummary::snapshot($mailed, $seriesYear);

        RecordsRoster::members()
            ->reject(fn (Member $member): bool => $member->no_email)
            ->each(fn (Member $member) => Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $member->getKey(),
                'email' => $member->email,
                'payload' => $payload,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]));
    }
}
