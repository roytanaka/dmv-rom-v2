<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Group with scheduling and vetting on, so a Tour can be made standalone against
     * a valid owner; pass `group_id` to attach it to an existing Group. Active, not open to all.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_scheduling' => true, 'has_vetting' => true]),
            'name' => fake()->unique()->words(3, true),
            'active' => true,
            'open_to_all' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * A retired Tour.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }

    /**
     * A Tour any Member may give without a qualification (ADR-0033 §6).
     */
    public function openToAll(): static
    {
        return $this->state(fn () => [
            'open_to_all' => true,
        ]);
    }

    /**
     * A starter Tour, given to a Member who becomes Full (ADR-0033 §7).
     */
    public function starter(): static
    {
        return $this->state(fn () => [
            'starter' => true,
        ]);
    }
}
