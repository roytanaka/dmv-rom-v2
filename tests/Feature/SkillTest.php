<?php

use App\Models\Member;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\QueryException;

/*
 * Model-test pattern for the Skills catalog (PRD #243, slice 1 / #245).
 *
 * Asserts external behaviour and invariants through the public model API — the
 * category↔skill relationship, the Member↔skill multi-select, the code-uniqueness
 * and composite-pivot invariants, and the active/ordered catalog query the settings
 * page reads — not column existence.
 */

it('round-trips a category and the skills grouped under it', function () {
    $category = SkillCategory::factory()->create();
    $skill = Skill::factory()->for($category, 'category')->create();

    expect($skill->fresh()->category->is($category))->toBeTrue()
        ->and($category->fresh()->skills->pluck('id'))->toContain($skill->id);
});

it('rejects a duplicate category code', function () {
    SkillCategory::factory()->create(['code' => 'communications']);
    SkillCategory::factory()->create(['code' => 'communications']);
})->throws(QueryException::class);

it('rejects a duplicate skill code', function () {
    Skill::factory()->create(['code' => 'copywriting']);
    Skill::factory()->create(['code' => 'copywriting']);
})->throws(QueryException::class);

it('attaches and detaches a Member\'s skills on the pivot', function () {
    $member = Member::factory()->create();
    $keep = Skill::factory()->create();
    $drop = Skill::factory()->create();

    $member->skills()->attach([$keep->id, $drop->id]);
    expect($member->fresh()->skills->pluck('id'))->toContain($keep->id, $drop->id);

    $member->skills()->detach($drop->id);
    expect($member->fresh()->skills->pluck('id'))
        ->toContain($keep->id)
        ->not->toContain($drop->id);
});

it('rejects a duplicate member-skill pair via the composite primary key', function () {
    $member = Member::factory()->create();
    $skill = Skill::factory()->create();

    $member->skills()->attach($skill->id);
    $member->skills()->attach($skill->id);
})->throws(QueryException::class);

it('queries the active catalog grouped by category in display order, excluding retired rows', function () {
    $second = SkillCategory::factory()->create(['display_order' => 2]);
    $first = SkillCategory::factory()->create(['display_order' => 1]);
    $retiredCategory = SkillCategory::factory()->retired()->create(['display_order' => 0]);

    $offered = Skill::factory()->for($second, 'category')->create();
    Skill::factory()->for($second, 'category')->retired()->create();
    Skill::factory()->for($retiredCategory, 'category')->create();

    $catalog = SkillCategory::active()
        ->with(['skills' => fn ($query) => $query->active()])
        ->get();

    expect($catalog->pluck('id'))->not->toContain($retiredCategory->id)
        ->and($catalog->first()->is($first))->toBeTrue()
        ->and($catalog->last()->is($second))->toBeTrue()
        ->and($catalog->firstWhere('id', $second->id)->skills->pluck('id'))
        ->toEqual(collect([$offered->id]));
});
