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
     * Add, rename, retire, restore, reorder, delete and map the Group's Tours.
     */
    public function manage(Member $actor, Group $group): bool
    {
        return $group->has_vetting
            && $actor->canActAs(Role::Vetting, $group);
    }

    /**
     * Set the Group's status rules (#793, ADR-0033 §7): the trainee Tour, the starter Tours and
     * the LOA rule. A Chair's call, not the Vetting officer's: the rules act on every Member's
     * qualifications when their standing changes.
     */
    public function manageRules(Member $actor, Group $group): bool
    {
        return $group->has_vetting
            && $actor->canActAs(Role::Chair, $group);
    }
}
