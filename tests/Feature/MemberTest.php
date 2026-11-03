<?php

use App\Enums\Category;
use App\Enums\DepartureReason;
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
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    $fresh = $member->fresh();

    expect($fresh->first_name)->toBe('Ada')
        ->and($fresh->last_name)->toBe('Lovelace')
        ->and($fresh->email)->toBe('ada@example.com')
        ->and($fresh->exists)->toBeTrue();
});

it('round-trips a departure reason, empty by default', function () {
    $member = Member::factory()->category(Category::Resigned)->create();

    expect($member->fresh()->departure_reason)->toBeNull();

    $member->update(['departure_reason' => DepartureReason::Moved]);

    expect($member->fresh()->departure_reason)->toBe(DepartureReason::Moved);
});

it('holds in Past only a Resigned or Deceased group Member whose personal data was removed', function () {
    $removed = ['personal_data_removed_at' => now()];

    $resigned = Member::factory()->category(Category::Resigned)->create($removed);
    $withdrawn = Member::factory()->category(Category::Withdrawn)->create($removed);
    $deceased = Member::factory()->category(Category::Deceased)->create($removed);
    Member::factory()->category(Category::Resigned)->create();
    Member::factory()->category(Category::Deceased)->create();
    Member::factory()->category(Category::Active)->create($removed);
    Member::factory()->category(Category::Donor)->create($removed);

    expect(Member::past()->pluck('id')->all())
        ->toEqualCanonicalizing([$resigned->id, $withdrawn->id, $deceased->id]);
});

it('casts category to the Category enum', function () {
    $member = Member::factory()->create(['category' => Category::Sustaining]);

    expect($member->fresh()->category)->toBe(Category::Sustaining);
});

it('defaults a newly registered Member to pre-active', function () {
    $member = Member::create([
        'first_name' => 'New',
        'last_name' => 'Recruit',
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
