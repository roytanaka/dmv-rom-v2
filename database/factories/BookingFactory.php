<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Shift;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state: a Booking of a fresh type, in that type's Group, on a fresh
     * Shift and Tour. The Shift and Tour do not belong to the type's Group; a test that reads them
     * through the Group builds the Booking with `GroupTourSchedule` and passes `shift_id`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_type_id' => BookingType::factory(),
            'group_id' => fn (array $attributes) => BookingType::find($attributes['booking_type_id'])->group_id,
            'shift_id' => Shift::factory(),
            'tour_id' => fn (array $attributes) => Tour::factory()->create(['group_id' => $attributes['group_id']])->id,
            'client' => fake()->company(),
            'visitors' => fake()->numberBetween(5, 40),
            'leader' => fake()->name(),
            'order_number' => (string) fake()->numberBetween(100000, 999999),
            'order_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'comments' => null,
        ];
    }
}
