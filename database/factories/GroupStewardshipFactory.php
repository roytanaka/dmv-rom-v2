<?php

namespace Database\Factories;

use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupStewardship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupStewardship>
 */
class GroupStewardshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the Group factory so a stewardship can be made standalone; pass
     * `group_id` to attach to an existing Group, and use `function()` to set the
     * stewarded function.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'function' => StewardshipFunction::MemberAdmin,
        ];
    }

    /**
     * Set the org-wide function this Group stewards.
     */
    public function stewarding(StewardshipFunction $function): static
    {
        return $this->state(fn () => [
            'function' => $function,
        ]);
    }
}
