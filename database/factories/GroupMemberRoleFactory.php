<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMemberRole>
 */
class GroupMemberRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a core role (chair) so it attaches to any Group regardless of
     * capability flags; pass `group_member_id` to attach to an existing
     * membership, and use `role()` to set a capability-backed role (whose Group
     * must have the backing flag on, per the write-time invariant).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_member_id' => GroupMember::factory(),
            'role' => Role::Chair,
        ];
    }

    /**
     * Set the role this membership carries.
     */
    public function role(Role $role): static
    {
        return $this->state(fn () => [
            'role' => $role,
        ]);
    }
}
