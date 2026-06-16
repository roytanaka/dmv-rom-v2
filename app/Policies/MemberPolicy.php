<?php

namespace App\Policies;

use App\Models\Member;

/**
 * Authorization for member records (ADR-0017). The super-tier short-circuit lives
 * in a single `Gate::before` (AppServiceProvider) and is never re-checked here, so
 * every method below reasons only about the non-super-tier cases.
 */
class MemberPolicy
{
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
}
