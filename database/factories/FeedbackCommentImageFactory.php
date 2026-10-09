<?php

namespace Database\Factories;

use App\Models\FeedbackComment;
use App\Models\FeedbackCommentImage;
use App\Support\FeedbackScreenshotStorage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeedbackCommentImage>
 */
class FeedbackCommentImageFactory extends Factory
{
    /**
     * Define the model's default state: the row only. A test that downloads the file puts
     * it on the faked disk at `storage_path` itself.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_comment_id' => FeedbackComment::factory(),
            'original_filename' => fake()->slug(2).'.png',
            'storage_path' => FeedbackScreenshotStorage::DIRECTORY.'/'.Str::uuid(),
            'mime_type' => 'image/png',
            'size_bytes' => fake()->numberBetween(10_000, 900_000),
        ];
    }
}
