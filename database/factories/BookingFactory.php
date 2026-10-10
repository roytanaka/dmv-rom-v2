<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state: a Booking of a fresh type, in that type's Group.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_type_id' => BookingType::factory(),
            'group_id' => fn (array $attributes) => BookingType::find($attributes['booking_type_id'])->group_id,
        ];
    }
}
