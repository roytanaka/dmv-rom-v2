<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's meetings (#190, PRD #186). Unlike the org-open
 * Overview and Roster, the Meetings list is members-only: visible only to a
 * member of that Group (plus the super-tier). The mutating officer methods
 * (create / update / delete) land with the meetings-CRUD slice (#193).
 *
 * The super-tier short-circuit lives in a single `Gate::before`
 * (AppServiceProvider) and is never re-checked here.
 */
class MeetingPolicy
{
    /**
     * Who may see a Group's meetings list: a member of the Group, and only while
     * the Group's meetings capability is on. The capability guard mirrors the
     * NewsPolicy — there is nothing to list when the Group runs no meetings.
     */
    public function viewAny(Member $actor, Group $group): bool
    {
        return $group->has_meetings
            && $actor->membershipIn($group) !== null;
    }
}
