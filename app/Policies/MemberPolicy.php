<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\GroupMember;
use App\Models\Member;

/**
 * Authorization for member records (ADR-0017). The super-tier short-circuit lives
 * in a single `Gate::before` (AppServiceProvider) and is never re-checked here, so
 * every method below reasons only about the non-super-tier cases.
 */
class MemberPolicy
{
    /**
     * The roles whose holders need a Group member's contact details to run the
     * Group (ADR-0011: Chair / Scheduler / Secretary). The single tunable source
     * for "which officers may see contact" — change the set here, nowhere else.
     *
     * @var list<Role>
     */
    public const CONTACT_NEED_ROLES = [Role::Chair, Role::Scheduler, Role::Secretary];

    /**
     * Who may edit a member's profile: the member themselves by default, or a
     * holder of member-administration authority (the Records stewardship,
     * ADR-0011) for anyone else. Everyone else is denied — authority never leaks
     * from one Group to another.
     */
    public function update(Member $actor, Member $target): bool
    {
        return $actor->is($target) || $actor->hasMemberAdminAuthority();
    }

    /**
     * Who may see a member's gated contact details (email, phone, …). Granted to
     * member-administration authority (the Records stewardship) org-wide, and to an
     * own-Group officer holding a contact-need role for members of that same Group.
     * Super-tier passes via the Gate::before short-circuit and is not re-checked.
     *
     * Authority never leaks across Groups: an officer of an unrelated Group — one
     * the target does not belong to — is denied. The decision reads the target's
     * memberships and asks, per shared Group, whether the viewer can effectively
     * act as a contact-need role there ({@see Member::canActAs()}, which folds in
     * Chair-implication).
     */
    public function viewContact(Member $viewer, Member $target): bool
    {
        if ($viewer->hasMemberAdminAuthority()) {
            return true;
        }

        return $target->memberships->contains(
            fn (GroupMember $membership) => collect(self::CONTACT_NEED_ROLES)
                ->contains(fn (Role $role) => $viewer->canActAs($role, $membership->group))
        );
    }

    /**
     * Who may see a member's home address — a Records-only tier stricter than
     * {@see viewContact()} (#232, ADR-0017 §field-level visibility). Granted only to
     * the member themselves and to member-administration authority (the Records
     * stewardship) org-wide; super-tier passes via the Gate::before short-circuit.
     *
     * Deliberately *not* extended to own-Group contact-need officers: the address is
     * never present in a peer-visible payload, even a gated one. A Chair who may see
     * a Group member's phone still may not see where they live.
     */
    public function viewAddress(Member $viewer, Member $target): bool
    {
        return $viewer->is($target) || $viewer->hasMemberAdminAuthority();
    }

    /**
     * Who may see a member's no-email flag — a Records-only member-administration
     * field (#483, ADR-0024 §9). Granted only to member-administration authority (the
     * Records stewardship) org-wide; super-tier passes via the Gate::before
     * short-circuit.
     *
     * Stricter than {@see viewAddress()}: the member themself is *not* admitted. The
     * flag is a Records decision about a member, not that member's own data — a sender
     * learns someone is unreachable only by trying to reach them (ADR-0024 §9), so the
     * flag never surfaces on the member's own profile either.
     */
    public function viewNoEmailFlag(Member $viewer, Member $target): bool
    {
        return $viewer->hasMemberAdminAuthority();
    }
}
