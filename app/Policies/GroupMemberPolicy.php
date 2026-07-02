<?php

namespace App\Policies;

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
     * Who may add a member to a Group: a member who {@see Member::administers()} it
     * (its Secretary or Chair).
     */
    public function create(Member $actor, Group $group): bool
    {
        return $actor->administers($group);
    }

    /**
     * Who may change a membership — its standing, leave window, or roles: a member
     * who administers the Group that owns it. Authority never leaks across Groups.
     */
    public function update(Member $actor, GroupMember $membership): bool
    {
        return $actor->administers($membership->group);
    }

    /**
     * Who may hard-remove a membership: a member who administers the owning Group.
     * The separate "no dependent records" precondition is enforced in the Form Request.
     */
    public function delete(Member $actor, GroupMember $membership): bool
    {
        return $actor->administers($membership->group);
    }
}
