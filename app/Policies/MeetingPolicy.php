<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;

/**
 * Authorization for a Group's meetings (#190, #193, PRD #186). Unlike the org-open
 * Overview and Roster, the Meetings list is members-only: visible only to a
 * member of that Group (plus the super-tier). Managing meetings (create / update /
 * delete, including link attachment and the published/hidden toggle) is narrower
 * still — a Secretary or Chair of the Group (plus the super-tier).
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

    /**
     * Who may add a meeting to a Group: a Secretary or Chair of that Group, and
     * only while its meetings capability is on. The capability guard matters
     * because Chair-implication would otherwise grant officer powers on a
     * non-meetings Group the member chairs.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $this->canManageMeetingsFor($actor, $group);
    }

    /**
     * Who may edit a meeting: an officer of the Group that owns it. Authority never
     * leaks across Groups — an officer of a different Group is denied.
     */
    public function update(Member $actor, Meeting $meeting): bool
    {
        return $this->canManageMeetingsFor($actor, $meeting->group);
    }

    /**
     * Who may delete a meeting: same as edit — an officer of the owning Group.
     */
    public function delete(Member $actor, Meeting $meeting): bool
    {
        return $this->canManageMeetingsFor($actor, $meeting->group);
    }

    /**
     * The shared predicate: the actor can act as Secretary or Chair of the Group
     * ({@see Member::canActAs()}, which folds in Chair-implication — a Chair without
     * an explicit Secretary role still qualifies), and the Group's meetings
     * capability is on.
     */
    private function canManageMeetingsFor(Member $actor, Group $group): bool
    {
        return $group->has_meetings
            && ($actor->canActAs(Role::Secretary, $group)
                || $actor->canActAs(Role::Chair, $group));
    }
}
