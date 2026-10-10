<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\SignUpSubstituted;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\Shift;

/**
 * The substitution Notice writer (#798, ADR-0032 §8, §9). When a seat-holder hands their seat on
 * a Booking to a substitute, the old Member, the new Member and the Group's Bookers (Chair
 * implied) are told. It writes one Notice {@see Delivery} per recipient, once each, skipping any
 * Member with the no-email flag (ADR-0024 §9); the every-minute Drain sends them.
 *
 * The row carries a payload snapshot, as the cancellation Notice does, so the mail renders the
 * same even if the Booking changes or goes before the Drain runs. Shape mirrors
 * {@see SignUpSubstituted::fromSnapshot}.
 */
class SignUpSubstitutionNoticeWriter
{
    /**
     * Write the substitution Notice Deliveries for one handed-over seat. The Shift must carry its
     * `kind`, `booking.tour` and `schedule.group`.
     */
    public function write(Shift $shift, Member $previous, Member $substitute): void
    {
        $snapshot = $this->snapshot($shift, $previous, $substitute);

        $recipients = collect([$previous, $substitute])
            ->concat($shift->schedule->group->bookers())
            ->unique(fn (Member $member) => $member->getKey());

        foreach ($recipients as $recipient) {
            if ($recipient->no_email) {
                continue;
            }

            Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $recipient->getKey(),
                'email' => $recipient->email,
                'payload' => $snapshot,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]);
        }
    }

    /**
     * Freeze what the Notice names into a payload the Drain renders from.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Shift $shift, Member $previous, Member $substitute): array
    {
        return [
            'notice' => SignUpSubstituted::NOTICE_TYPE,
            'previous' => [
                'first_name' => $previous->first_name,
                'last_name' => $previous->last_name,
            ],
            'substitute' => [
                'first_name' => $substitute->first_name,
                'last_name' => $substitute->last_name,
            ],
            'group' => [
                'name' => $shift->schedule->group->name,
                'slug' => $shift->schedule->group->slug,
            ],
            'schedule' => [
                'id' => $shift->schedule->id,
                'name' => $shift->schedule->name,
            ],
            'shift' => [
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
            ],
            'booking' => [
                'tour' => $shift->booking?->tour?->name,
                'client' => $shift->booking?->client,
            ],
        ];
    }
}
