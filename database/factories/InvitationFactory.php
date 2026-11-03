<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state: a future Draft with a fresh Host. Pass
     * `host_id` to attach it to an existing Member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_id' => Member::factory(),
            'name' => fake()->sentence(3),
            'date' => now()->addMonth()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'description' => fake()->sentence(),
        ];
    }
}
