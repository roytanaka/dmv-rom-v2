<?php

use App\Models\Member;
use App\Personas\PersonaCatalogue;
use App\Support\ProfilePhotoStorage;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Demo profile photos (#235): the DemoSeeder fetches stable per-persona DiceBear
 * avatars and stores them through #233's public-disk/UUID pipeline, so demo data
 * mirrors real uploads (no remote URLs persisted). Every fetch is best-effort —
 * staging runs `migrate:fresh --seed` on every push, so an unreachable or
 * rate-limited endpoint must fall back to initials, never fail the seed.
 */

// Seeded Document library files go to a faked private disk, never the real one.
beforeEach(fn () => Storage::fake('local'));

it('stores fetched DiceBear avatars via the public-disk/UUID pipeline, not as remote URLs', function () {
    Storage::fake('public');
    Http::fake([
        'api.dicebear.com/*' => Http::response('fake-webp-bytes', 200, ['Content-Type' => 'image/webp']),
    ]);

    $this->seed(DemoSeeder::class);

    $persona = Member::where('email', PersonaCatalogue::CHAIR_EMAIL)->firstOrFail();

    expect($persona->photo_path)->not->toBeNull()
        ->and($persona->photo_path)->toStartWith(ProfilePhotoStorage::DIRECTORY.'/')
        ->and($persona->photo_path)->not->toContain('dicebear')
        ->and($persona->photo_path)->not->toContain('http')
        ->and(Storage::disk('public')->exists($persona->photo_path))->toBeTrue();
});

it('requests a deterministic lorelei WebP keyed on the URL-encoded email', function () {
    Storage::fake('public');
    Http::fake([
        'api.dicebear.com/*' => Http::response('fake-webp-bytes', 200, ['Content-Type' => 'image/webp']),
    ]);

    $this->seed(DemoSeeder::class);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/10.x/lorelei/webp')
        && str_contains($request->url(), 'size=512')
        && str_contains($request->url(), 'seed='.rawurlencode(PersonaCatalogue::CHAIR_EMAIL)));
});

it('photographs a fraction of the roster and leaves the rest on the initials fallback', function () {
    Storage::fake('public');
    Http::fake([
        'api.dicebear.com/*' => Http::response('fake-webp-bytes', 200, ['Content-Type' => 'image/webp']),
    ]);

    $this->seed(DemoSeeder::class);

    // A realistic mix: some members carry a photo, some fall back to initials.
    expect(Member::whereNotNull('photo_path')->exists())->toBeTrue()
        ->and(Member::whereNull('photo_path')->exists())->toBeTrue();
});

it('falls back to initials and still seeds a valid roster when the endpoint is unreachable', function () {
    Storage::fake('public');
    Http::fake(fn () => throw new ConnectionException('dicebear unreachable'));

    $this->seed(DemoSeeder::class);

    // The seed completed and built a real roster, all on the initials fallback.
    expect(Member::count())->toBeGreaterThan(0)
        ->and(Member::whereNotNull('photo_path')->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('never persists a photo when the endpoint rate-limits the seed', function () {
    Storage::fake('public');
    Http::fake([
        'api.dicebear.com/*' => Http::response('Too Many Requests', 429),
    ]);

    $this->seed(DemoSeeder::class);

    expect(Member::whereNotNull('photo_path')->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('deletes the photos a wiped database left behind, and keeps every photo a Member points at', function () {
    Storage::fake('public');
    Http::fake([
        'api.dicebear.com/*' => Http::response('fake-webp-bytes', 200, ['Content-Type' => 'image/webp']),
    ]);
    // A photo from before `migrate:fresh`: on the disk, with no Member pointing at it.
    Storage::disk('public')->put(ProfilePhotoStorage::DIRECTORY.'/left-behind.webp', 'old bytes');
    // A sibling folder on the same disk is not the seeder's to clean.
    Storage::disk('public')->put('elsewhere/keep-me.png', 'bytes');

    $this->seed(DemoSeeder::class);

    Storage::disk('public')->assertMissing(ProfilePhotoStorage::DIRECTORY.'/left-behind.webp');
    Storage::disk('public')->assertExists('elsewhere/keep-me.png');

    $referenced = Member::whereNotNull('photo_path')->pluck('photo_path')->sort()->values()->all();
    $onDisk = collect(Storage::disk('public')->allFiles(ProfilePhotoStorage::DIRECTORY))->sort()->values()->all();

    expect($onDisk)->not->toBeEmpty()->toBe($referenced);
});
