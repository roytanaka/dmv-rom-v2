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
}
