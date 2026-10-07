<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\Group;
use App\Support\DocumentStorage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state: a file Document at the library root, the row only.
     * A test that downloads the file puts it on the faked disk at `storage_path` itself.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_documents' => true]),
            'folder_id' => null,
            'title' => null,
            'description' => null,
            'kind' => DocumentKind::File,
            'url' => null,
            'original_filename' => fake()->slug(3).'.pdf',
            'storage_path' => DocumentStorage::DIRECTORY.'/'.Str::uuid(),
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 900_000),
            'uploaded_by_id' => null,
            'uploaded_at' => now(),
        ];
    }

    /**
     * A link Document (#716, ADR-0030 §7): a title and a web address, no stored file.
     */
    public function link(): static
    {
        return $this->state(fn () => [
            'kind' => DocumentKind::Link,
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'original_filename' => null,
            'storage_path' => null,
            'mime_type' => null,
            'size_bytes' => null,
        ]);
    }
}
