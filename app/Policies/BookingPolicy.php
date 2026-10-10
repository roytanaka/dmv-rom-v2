<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's Bookings (#795, ADR-0032 §3, §5). The Booker adds them; the Chair
 * implies it ({@see Member::canActAs()}). The capability guards matter: Chair-implication would
 * otherwise grant the power on a Group that runs no bookings or no scheduling. A Scheduler gets
 * nothing here. The super-tier short-circuit lives in `Gate::before` and is never re-checked.
 *
 * Reading follows §5: anyone who can read the Schedule sees a Booking's Shift and its Tour; the
 * Group's Members also see the client half ({@see viewDetails()}); the Booker, the Statistician and
 * the Chair also see the order number and date ({@see viewOfficerFields()}), and Earned (#797).
 */
class BookingPolicy
{
    /**
     * Add a Booking to the Group (§3): a Booker or Chair, while the Group runs bookings and
     * scheduling.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $group->has_bookings
            && $group->has_scheduling
            && $actor->canActAs(Role::Booker, $group);
    }

    /**
     * Change the Group's Bookings (#796, §3): a Booker or Statistician, the Chair implying both,
     * while the Group runs bookings and scheduling. Unlike {@see create()}, the Statistician may.
     * Group-level, so the Schedule tab can send the change form's pickers.
     */
    public function change(Member $actor, Group $group): bool
    {
        return $group->has_bookings
            && $group->has_scheduling
            && ($actor->canActAs(Role::Booker, $group) || $actor->canActAs(Role::Statistician, $group));
    }

    /**
     * Change one Booking's fields, times or docents needed (#796, §3).
     */
    public function update(Member $actor, Booking $booking): bool
    {
        return $this->change($actor, $booking->group);
    }

    /**
     * Delete a Booking with its Shift and Sign-ups (#796, §3): the same people as a change.
     */
    public function delete(Member $actor, Booking $booking): bool
    {
        return $this->change($actor, $booking->group);
    }

    /**
     * Place and remove docents on a Booking's Shift (#798, §3, §8): a Booker or Chair, while the
     * Group runs bookings and scheduling. A Scheduler does this through the schedule-admin gate
     * ({@see SignUpPolicy}), not here.
     */
    public function place(Member $actor, Booking $booking): bool
    {
        return $this->create($actor, $booking->group);
    }

    /**
     * Read a Booking's client half (§5): the client, visitors, type, leader and comments. Current
     * Members of the Group only (a standing that counts as belonging); everyone else who can read
     * the Schedule sees only the time, Tour and seats.
     */
    public function viewDetails(Member $actor, Booking $booking): bool
    {
        return $actor->membershipIn($booking->group)?->status->countsAsBelonging() ?? false;
    }

    /**
     * Read a Booking's office fields (§5): the order number and order date, and Earned (#797). The
     * Booker and the Statistician; the Chair implies both.
     */
    public function viewOfficerFields(Member $actor, Booking $booking): bool
    {
        return $actor->canActAs(Role::Booker, $booking->group)
            || $actor->canActAs(Role::Statistician, $booking->group);
    }

    /**
     * Send a Booking's Request or Confirmation again (#799, §9): the Booker or Chair, while the
     * Group runs bookings.
     */
    public function sendMails(Member $actor, Booking $booking): bool
    {
        return $booking->group->has_bookings
            && $actor->canActAs(Role::Booker, $booking->group);
    }

    /**
     * Set or clear the correction to Earned (§7, #797): the Statistician; the Chair implies it.
     * Not the Booker, who sees Earned but cannot correct it.
     */
    public function correctEarned(Member $actor, Booking $booking): bool
    {
        return $actor->canActAs(Role::Statistician, $booking->group);
    }

    /**
     * Enter the Group's monthly exhibition revenue from the Tour Summary (#800, §12): the
     * Statistician; the Chair implies it. While the Group runs bookings.
     */
    public function enterExhibitionRevenue(Member $actor, Group $group): bool
    {
        return $group->has_bookings
            && $actor->canActAs(Role::Statistician, $group);
    }

    /**
     * Read the Group's past client names for the form's suggestions (§10): whoever may add a
     * Booking.
     */
    public function suggestClients(Member $actor, Group $group): bool
    {
        return $this->create($actor, $group);
    }
}
