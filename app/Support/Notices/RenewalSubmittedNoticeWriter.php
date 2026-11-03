<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\RenewalSubmitted;
use App\Models\Delivery;
use App\Models\Member;

/**
 * The renewal Notice (#940, ADR-0038 §9, §14.5): a Member saved the renewal form. The Legacy app
 * mailed the Records mailbox on every first submission, and on a later edit only when something
 * changed; it mailed the ROM membership mailboxes the changed fields only. Here: one Notice
 * {@see Delivery} per member of the Records Audience, skipping the no-email flag, on the same
 * rule; the external addresses get a copy when any tracked field changed.
 */
class RenewalSubmittedNoticeWriter
{
    public function __construct(private ExternalAddressCopyWriter $external) {}

    /**
     * @param  list<array{field: string, from: string, to: string}>  $changes
     * @param  array{from: string, to: string}|null  $categoryChange
     */
    public function write(Member $member, int $year, bool $update, array $changes, ?array $categoryChange, bool $expired): void
    {
        if ($update && $changes === [] && $categoryChange === null) {
            return;
        }

        $payload = [
            'notice' => RenewalSubmitted::NOTICE_TYPE,
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'membership_number' => $member->membership_number,
            ],
            'year' => $year,
            'update' => $update,
            'changes' => $changes,
            'category_change' => $categoryChange,
            'expired' => $expired,
        ];

        RecordsRoster::members()
            ->reject(fn (Member $recipient): bool => $recipient->no_email)
            ->each(fn (Member $recipient) => Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $recipient->getKey(),
                'email' => $recipient->email,
                'payload' => $payload,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]));

        if ($changes !== []) {
            $this->external->write($payload);
        }
    }
}
