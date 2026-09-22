<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\Shift;

/**
 * The Sign-up cancellation Notice writer (#358, #585, PRD #352, ADR-0021 §Sign-up
 * "Notification"). When a Member drops their own seat — or deletes a self-authored Shift,
 * which drops the seat with it (ADR-0026 §1) — every Scheduler and Chair of the owning Group
 * is told, unconditionally, with no threshold or proximity window. It writes one Notice
 * {@see Delivery} per recipient and nothing else; the every-minute Drain sends them.
 *
 * The Sign-up (and, on a self-serve delete, the Shift) is gone by drain time, so the row
 * carries a payload snapshot of everything the mail names — the dropped Member, the Group, and
 * the Shift's Schedule, times, and kind. Shape mirrors {@see SignUpCancelled::fromSnapshot}.
 *
 * Both callers delete the row(s) first, then call {@see write} with the still-in-memory models,
 * so the two cancellation paths fire the identical Notice from one place.
 */
class SignUpCancellationNoticeWriter
{
    /**
     * Write the cancellation Notice Deliveries for one dropped seat. The Shift must carry its
     * `kind` and `schedule.group` (both read by the snapshot and the recipient roster).
     *
     * @param  Shift  $shift  the Shift the dropped seat was on
     * @param  Member  $member  the Member whose seat it was
     */
    public function write(Shift $shift, Member $member): void
    {
        $snapshot = $this->snapshot($shift, $member);

        foreach ($shift->schedule->group->schedulers() as $scheduler) {
            // The no-email flag silences every mail, checked once here — the sole point a
            // Notice Delivery is written (#483, ADR-0024 §9).
            if ($scheduler->no_email) {
                continue;
            }

            Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $scheduler->getKey(),
                'email' => $scheduler->email,
                'payload' => $snapshot,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]);
        }
    }

    /**
     * Freeze what a cancellation Notice names into a payload the Drain can render from without
     * the Sign-up, which is already gone. Shape mirrors {@see SignUpCancelled::fromSnapshot}.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Shift $shift, Member $member): array
    {
        return [
            'member' => [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
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
                'kind' => $shift->kind?->name,
            ],
        ];
    }
}
