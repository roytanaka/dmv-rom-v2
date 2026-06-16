<?php

use App\Enums\Category;
use App\Models\Member;

/*
 * Model-test pattern for the spine (PRD #126, slice 1 / #127).
 *
 * Asserts external behaviour and invariants through the model's public API —
 * identity round-trips, enum casts, illegal-value rejection, and the model-level
 * query helpers — not column existence. Later spine slices copy this shape.
 */

it('round-trips a Member identity', function () {
    $member = Member::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $fresh = $member->fresh();

    expect($fresh->name)->toBe('Ada Lovelace')
        ->and($fresh->email)->toBe('ada@example.com')
        ->and($fresh->exists)->toBeTrue();
});

it('casts category to the Category enum', function () {
    $member = Member::factory()->create(['category' => Category::Sustaining]);

    expect($member->fresh()->category)->toBe(Category::Sustaining);
});

it('defaults a newly registered Member to pre-active', function () {
    $member = Member::create([
        'name' => 'New Recruit',
        'email' => 'recruit@example.com',
        'password' => 'password',
    ]);

    expect($member->fresh()->category)->toBe(Category::PreActive);
});

it('rejects an illegal category value', function () {
    Member::factory()->create(['category' => 'not_a_category']);
})->throws(ValueError::class);

it('casts super_tier to a boolean', function () {
    $member = Member::factory()->superTier()->create();

    expect($member->fresh()->super_tier)->toBeTrue();
});

it('answers whether a Member is all-DMV via super_tier', function () {
    $ordinary = Member::factory()->create();
    $allDmv = Member::factory()->superTier()->create();

    expect($ordinary->isAllDmv())->toBeFalse()
        ->and($allDmv->isAllDmv())->toBeTrue();
});

it('produces varied categories via the factory state', function () {
    $member = Member::factory()->category(Category::Sustaining)->create();

    expect($member->fresh()->category)->toBe(Category::Sustaining);
});
