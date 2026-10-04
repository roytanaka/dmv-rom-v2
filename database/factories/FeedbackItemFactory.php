<?php

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Models\FeedbackItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackItem>
 */
class FeedbackItemFactory extends Factory
{
    /**
     * Define the model's default state: a New item with the server and client context a
     * real send captures.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tester_name' => fake()->name(),
            'type' => fake()->randomElement(FeedbackType::cases()),
            'message' => fake()->sentence(),
            'page_url' => '/dashboard',
            'user_agent' => 'Mozilla/5.0',
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'route_name' => 'dashboard',
            'locale' => 'en',
            'member_name' => fake()->name(),
            'member_email' => fake()->safeEmail(),
        ];
    }
}
