<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's booking types and group-tour settings (#794, ADR-0032 §3, §6). The
 * Booker keeps them; the Chair implies it ({@see Member::canActAs()}). The capability guard
 * matters: Chair-implication would otherwise grant the power on a Group that runs no bookings.
 * A Scheduler or Statistician gets nothing here. The super-tier short-circuit lives in
 * `Gate::before` and is never re-checked.
 */
class BookingTypePolicy
{
    /**
     * Add, rename, re-rate, retire, restore, reorder and delete the Group's booking types, and set
     * its group-tour shift kind and Schedule label.
     */
    public function manage(Member $actor, Group $group): bool
    {
        return $group->has_bookings
            && $actor->canActAs(Role::Booker, $group);
    }
}
