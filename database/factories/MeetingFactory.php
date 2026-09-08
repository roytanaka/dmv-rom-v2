<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Group with meetings on, so a meeting can be made standalone
     * against a valid owner; pass `group_id` to attach it to an existing Group.
     * Published by default — use {@see hidden()} for a draft.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_meetings' => true]),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'held_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'location' => fake()->words(2, true),
            'video_url' => fake()->url(),
            'is_published' => true,
        ];
    }

    /**
     * A hidden (draft) meeting — invisible to ordinary members until published.
     */
    public function hidden(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
        ]);
    }
}
