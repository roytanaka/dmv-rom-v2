<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's Tour list (#788, ADR-0033 §4). The Vetting role keeps the list,
 * its kind mapping and (later) its qualifications; the Chair implies it ({@see Member::canActAs()}).
 * The capability guard matters: Chair-implication would otherwise grant Tour powers on a Group
 * that runs no vetting. A Scheduler gets nothing here. The super-tier short-circuit lives in
 * `Gate::before` and is never re-checked.
 */
class TourPolicy
{
    /**
     * Read the Group's Tours page (#792, ADR-0033 §5) — who gives each Tour and their Last vet
     * dates. Members of the Group only, like the Meetings list; the super-tier via `Gate::before`.
     */
    public function view(Member $actor, Group $group): bool
    {
        return $group->has_vetting
            && $actor->membershipIn($group) !== null;
    }

    /**
     * Add, rename, retire, restore, reorder, delete and map the Group's Tours.
     */
    public function manage(Member $actor, Group $group): bool
    {
        return $group->has_vetting
            && $actor->canActAs(Role::Vetting, $group);
    }
}
