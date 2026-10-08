<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    /**
     * Define the model's default state: a Document category at the library root of a Group
     * with a Document library.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_documents' => true]),
            'folder_id' => null,
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * A Document category of the given Folder's list, in the same Group.
     */
    public function in(DocumentFolder $folder): static
    {
        return $this->state(['group_id' => $folder->group_id, 'folder_id' => $folder->id]);
    }
}
