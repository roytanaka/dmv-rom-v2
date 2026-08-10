<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\ShiftKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftKind>
 */
class ShiftKindFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Group with scheduling on, so a kind can be made standalone against a
     * valid owner; pass `group_id` to attach it to an existing Group. Active by default —
     * use {@see inactive()} for a retired kind that still labels its old Shifts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_scheduling' => true]),
            'name' => fake()->unique()->words(2, true),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A retired kind — no longer offered to new Shifts, but still labelling old ones.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
