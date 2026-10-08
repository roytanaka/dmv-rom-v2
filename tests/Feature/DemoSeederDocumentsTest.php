<?php

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Support\DocumentStorage;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Demo Document library files (#718, spec #290): seeded files go through the storage
 * service onto the private disk, a UUID name under documents/ with the original filename
 * kept on the row, like a real upload. Seeds per test on a fresh faked disk, so the
 * files are there to check.
 */

beforeEach(function () {
    Storage::fake('local');
    Http::fake();
});

it('stores every seeded file on the private disk under a UUID name, keeping the filename on the row', function () {
    $this->seed(DemoSeeder::class);

    $files = Document::where('kind', DocumentKind::File)->get();

    expect($files)->not->toBeEmpty();

    $files->each(function (Document $document) {
        expect($document->storage_path)->toMatch('#^'.DocumentStorage::DIRECTORY.'/[0-9a-f-]{36}$#')
            ->and(Storage::disk('local')->exists($document->storage_path))->toBeTrue()
            ->and(Storage::disk('local')->size($document->storage_path))->toBe($document->size_bytes)
            ->and($document->original_filename)->toMatch('/\.(pdf|txt|csv)$/')
            ->and($document->mime_type)->not->toBeEmpty();
    });
});

it('stores no second copy of a seeded file when the seed runs again', function () {
    $this->seed(DemoSeeder::class);
    $before = count(Storage::disk('local')->allFiles(DocumentStorage::DIRECTORY));

    $this->seed(DemoSeeder::class);

    expect(Storage::disk('local')->allFiles(DocumentStorage::DIRECTORY))->toHaveCount($before);
});

it('deletes the files a wiped database left behind, and keeps every file a Document points at', function () {
    // A file from before `migrate:fresh`: on the disk, with no Document row pointing at it.
    Storage::disk('local')->put(DocumentStorage::DIRECTORY.'/left-behind', 'old bytes');
    // A sibling folder on the same disk is not the seeder's to clean.
    Storage::disk('local')->put('feedback-screenshots/keep-me', 'screenshot');

    $this->seed(DemoSeeder::class);

    Storage::disk('local')->assertMissing(DocumentStorage::DIRECTORY.'/left-behind');
    Storage::disk('local')->assertExists('feedback-screenshots/keep-me');

    $referenced = Document::whereNotNull('storage_path')->pluck('storage_path')->sort()->values()->all();
    $onDisk = collect(Storage::disk('local')->allFiles(DocumentStorage::DIRECTORY))->sort()->values()->all();

    expect($onDisk)->toBe($referenced);
});
