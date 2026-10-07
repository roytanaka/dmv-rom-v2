<?php

namespace Database\Factories;

use App\Models\DocumentTag;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTag>
 */
class DocumentTagFactory extends Factory
{
    /**
     * Define the model's default state: a Tag of a Group that runs a Document library.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_documents' => true]),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
