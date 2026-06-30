<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;

/**
 * Authorization for officer roster CRUD on a Group (#192, PRD #186) — adding a
 * member, changing standing (including a leave window), assigning / revoking roles,
 * resigning a member, and the added-in-error hard-remove. Mirrors the GroupPolicy /
 * MeetingPolicy seam: every mutation routes a Form Request through here.
 *
 * Roster management is gated to a Group's Secretary or Chair (plus the super-tier).
 * Like the GroupPolicy's Overview edits — and unlike the MeetingPolicy — there is
 * deliberately no capability guard: every Group has a roster regardless of its
 * capability flags, so managing it is a core officer power, not a per-capability one.
 *
 * The super-tier short-circuit lives in a single `Gate::before` (AppServiceProvider)
 * and is never re-checked here. The "no dependent records" precondition on a
 * hard-remove is a data-integrity invariant, not an authority question, so it lives
 * in the DeleteGroupMemberRequest (applying to the super-tier too), not here.
 */
class GroupMemberPolicy
{
    /**
     * Who may add a member to a Group: a Secretary or Chair of that Group.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $this->isOfficer($actor, $group);
    }

    /**
     * Who may change a membership — its standing, leave window, or roles: an officer
     * of the Group that owns it. Authority never leaks across Groups.
     */
    public function update(Member $actor, GroupMember $membership): bool
    {
        return $this->isOfficer($actor, $membership->group);
    }

    /**
     * Who may hard-remove a membership: an officer of the owning Group. The separate
     * "no dependent records" precondition is enforced in the Form Request.
     */
    public function delete(Member $actor, GroupMember $membership): bool
    {
        return $this->isOfficer($actor, $membership->group);
    }

    /**
     * The shared predicate: the actor can act as Secretary or Chair of the Group
     * ({@see Member::canActAs()}, which folds in Chair-implication — a Chair without
     * an explicit Secretary role still qualifies, except for Treasurer's finance
     * authority). An officer of a different Group is denied.
     */
    private function isOfficer(Member $actor, Group $group): bool
    {
        return $actor->canActAs(Role::Secretary, $group)
            || $actor->canActAs(Role::Chair, $group);
    }
}
