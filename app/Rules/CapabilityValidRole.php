<?php

namespace App\Rules;

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMemberRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a role whose backing Group capability is off — the validation-layer twin
 * of the write-time invariant {@see GroupMemberRole} already enforces.
 * Officer roster CRUD (#192) offers only capability-valid roles, so a submitted role
 * the Group has no capability for fails closed with a clean validation error rather
 * than surfacing the model's DomainException as a 500.
 *
 * Core roles (Chair / Secretary / Treasurer, no required capability) always pass; an
 * unknown value passes here and is caught by the accompanying `Rule::enum` instead.
 */
class CapabilityValidRole implements ValidationRule
{
    public function __construct(private Group $group) {}

    /**
     * Fail when the role requires a capability the Group does not have on.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = $value instanceof Role ? $value : Role::tryFrom((string) $value);

        if ($role === null) {
            return;
        }

        $capability = $role->requiredCapability();

        if ($capability !== null && ! $this->group->{$capability}) {
            $fail('group.roster.role_unavailable')->translate();
        }
    }
}
