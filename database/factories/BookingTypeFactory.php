<?php

namespace Database\Factories;

use App\Models\BookingType;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingType>
 */
class BookingTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Group with scheduling and bookings on, so a type can be made standalone against
     * a valid owner; pass `group_id` to attach it to an existing Group. Active, both rates zero.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_scheduling' => true, 'has_bookings' => true]),
            'name' => fake()->unique()->words(2, true),
            'rate_per_visitor' => 0,
            'rate_per_docent_hour' => 0,
            'active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A retired type.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
