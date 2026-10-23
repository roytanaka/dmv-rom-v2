<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\Group;

/**
 * The Group copy address (#799, ADR-0032 §9). A Group running bookings may set one address that
 * every Booking mail is copied to; the old app hard-coded a ROM staff address here. A Notice writer
 * calls {@see write} once per mail it sends, with the same payload its Member rows carry, and the
 * copy rides the Delivery queue like any other row.
 *
 * The address is no Member, so its row has no `member_id` and no no-email flag to check. The Drain
 * reads a Member row's locale from the Member; a copy row carries its own in the payload's `locale`.
 * Used by the Booking Request and Confirmation ({@see BookingMailWriter}) and the substitution
 * notice (#798).
 */
class GroupCopyWriter
{
    /**
     * Write the copy of one Notice to the Group's copy address. Nothing when the Group has none.
     *
     * @param  array<string, mixed>  $payload  the Notice payload, `notice` discriminator included
     * @param  string|null  $locale  the language the copy renders in; the app default when null
     */
    public function write(Group $group, array $payload, ?string $locale = null): void
    {
        if (blank($group->booking_copy_email)) {
            return;
        }

        Delivery::create([
            'kind' => DeliveryKind::Notice,
            'member_id' => null,
            'email' => $group->booking_copy_email,
            'payload' => [...$payload, 'locale' => $locale ?? config('app.locale')],
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }
}
