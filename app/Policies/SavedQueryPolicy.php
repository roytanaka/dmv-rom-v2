<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\SavedQuery;

/**
 * Authorization for Saved queries (#923, ADR-0038 §7): member administration only, for every
 * action. The super-tier passes through the Gate::before short-circuit (AppServiceProvider).
 */
class SavedQueryPolicy
{
    public function viewAny(Member $actor): bool
    {
        return $actor->hasMemberAdminAuthority();
    }

    public function create(Member $actor): bool
    {
        return $actor->hasMemberAdminAuthority();
    }

    public function run(Member $actor, SavedQuery $query): bool
    {
        return $actor->hasMemberAdminAuthority();
    }

    public function update(Member $actor, SavedQuery $query): bool
    {
        return $actor->hasMemberAdminAuthority();
    }

    public function delete(Member $actor, SavedQuery $query): bool
    {
        return $actor->hasMemberAdminAuthority();
    }
}
