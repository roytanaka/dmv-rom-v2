<?php

namespace Database\Factories;

use App\Enums\DocumentVisibility;
use App\Models\DocumentFolder;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFolder>
 */
class DocumentFolderFactory extends Factory
{
    /**
     * Define the model's default state: a top-level Folder in a Group with a Document library.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_documents' => true]),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * A top-level Folder shared with every Member (#715, ADR-0030 §5). The default is `group`,
     * set on save ({@see DocumentFolder::booted()}).
     */
    public function sharedWithMembers(): static
    {
        return $this->state(['visibility' => DocumentVisibility::Members]);
    }

    /**
     * A subfolder of the given Folder, in the same Group.
     */
    public function in(DocumentFolder $parent): static
    {
        return $this->state(['group_id' => $parent->group_id, 'parent_id' => $parent->id]);
    }
}
