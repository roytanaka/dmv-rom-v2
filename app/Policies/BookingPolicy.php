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
     * Read the Group's past client names for the form's suggestions (§10): whoever may add a
     * Booking.
     */
    public function suggestClients(Member $actor, Group $group): bool
    {
        return $this->create($actor, $group);
    }
}
