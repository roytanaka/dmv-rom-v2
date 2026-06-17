<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the Group factory with announcements on, so a feed item can be made
     * standalone against a valid posting Group; pass `posting_group_id` to attach
     * it to an existing Group.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'posting_group_id' => Group::factory()->state(['has_announcements' => true]),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
