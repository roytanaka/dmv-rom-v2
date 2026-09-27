<?php

namespace Database\Factories;

use App\Models\FeedbackComment;
use App\Models\FeedbackItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackComment>
 */
class FeedbackCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_item_id' => FeedbackItem::factory(),
            'tester_name' => fake()->name(),
            'body' => fake()->sentence(),
        ];
    }
}
