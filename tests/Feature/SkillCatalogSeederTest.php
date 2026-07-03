<?php

use App\Models\Member;
use App\Models\Skill;
use App\Models\SkillCategory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SkillCatalogSeeder;
use Illuminate\Support\Facades\Http;

/*
 * The org-owned Skills catalog seed (PRD #243, slice 1 / #245): a faker-free,
 * idempotent vocabulary of categories + skills. These assert observable
 * invariants — the catalog is populated and queryable, re-seeding heals rather
 * than duplicates, and it rides the standard seed path — not the exact vocabulary,
 * so the suite survives the curated list changing.
 */

it('populates an active catalog grouped by category in display order', function () {
    $this->seed(SkillCatalogSeeder::class);

    $catalog = SkillCategory::active()
        ->with(['skills' => fn ($query) => $query->active()])
        ->get();

    expect($catalog)->not->toBeEmpty()
        ->and($catalog->pluck('display_order')->toArray())
        ->toBe($catalog->pluck('display_order')->sort()->values()->toArray())
        ->and($catalog->every(fn (SkillCategory $category) => $category->skills->isNotEmpty()))->toBeTrue();
});

it('is idempotent — re-seeding leaves catalog counts unchanged', function () {
    $this->seed(SkillCatalogSeeder::class);

    $before = ['categories' => SkillCategory::count(), 'skills' => Skill::count()];

    $this->seed(SkillCatalogSeeder::class);

    expect(['categories' => SkillCategory::count(), 'skills' => Skill::count()])->toBe($before);
});

it('runs as part of the standard database seed path', function () {
    // DatabaseSeeder chains DemoSeeder, which fetches best-effort avatars; fake the
    // HTTP client so this never touches the network.
    Http::fake();

    $this->seed(DatabaseSeeder::class);

    expect(SkillCategory::exists())->toBeTrue()
        ->and(Skill::exists())->toBeTrue();
});

it('lets a Member select skills straight from the seeded catalog', function () {
    $this->seed(SkillCatalogSeeder::class);

    $member = Member::factory()->create();
    $chosen = Skill::query()->active()->take(3)->pluck('id');

    $member->skills()->sync($chosen);

    expect($member->fresh()->skills->pluck('id')->sort()->values())
        ->toEqual($chosen->sort()->values());
});
