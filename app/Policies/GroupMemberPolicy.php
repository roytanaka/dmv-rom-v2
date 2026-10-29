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
 * Roster management is gated to a Group's Secretary or Chair, a parent officer of any
 * Group above it (ADR-0036 §11), plus the super-tier.
 * Like the GroupPolicy's Overview edits — and unlike the MeetingPolicy — there is
 * deliberately no capability guard: every Group has a roster regardless of its
 * capability flags, so managing it is a core officer power, not a per-capability one.
 *
 * The super-tier short-circuit lives in a single `Gate::before` (AppServiceProvider)
 * and is never re-checked here. The "no dependent records" precondition on a
 * hard-remove is a data-integrity invariant, not an authority question, so it lives
 * in the DeleteGroupMemberRequest (applying to the super-tier too), not here. The
 * root DMV Group's "no stored rows" invariant (ADR-0036 §9) is the same kind of
 * thing: StoreGroupMemberRequest refuses it for everyone, super-tier included; the
 * `create` rule below only closes the officer path.
 */
class GroupMemberPolicy
{
    /**
     * Who may add a member to a Group: a member who {@see Member::administers()} it
     * (its Secretary or Chair) or a Group above it — never on the root DMV Group, whose membership is
     * derived from Category and never stored (ADR-0036 §9).
     */
    public function create(Member $actor, Group $group): bool
    {
        return ! $group->isRoot()
            && $actor->administersOrOversees($group);
    }

    /**
     * Who may change a membership — its standing, leave window, or roles: a member
     * who administers the Group that owns it or a Group above it. Authority never leaks
     * sideways across Groups.
     */
    public function update(Member $actor, GroupMember $membership): bool
    {
        return $actor->administersOrOversees($membership->group);
    }

    /**
     * Who may hard-remove a membership: a member who administers the owning Group or a
     * Group above it.
     * The separate "no dependent records" precondition is enforced in the Form Request.
     */
    public function delete(Member $actor, GroupMember $membership): bool
    {
        return $actor->administersOrOversees($membership->group);
    }
}
