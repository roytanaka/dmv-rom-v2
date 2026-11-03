<?php

use App\Enums\AccessTier;
use App\Enums\Category;
use App\Enums\CategoryGroup;

/*
 * Every Category answers every question the app asks of it (#916, ADR-0038 §4). The
 * table is written out by hand, and a guard fails when a case is missing from it, so a
 * new Category cannot slip past.
 */

/**
 * @return array<string, array{AccessTier, bool, bool, CategoryGroup}>
 */
function categoryTable(): array
{
    return [
        'active' => [AccessTier::Full, true, true, CategoryGroup::Current],
        'pre_active' => [AccessTier::Limited, false, true, CategoryGroup::Current],
        'provisional' => [AccessTier::Limited, false, true, CategoryGroup::Current],
        'sustaining' => [AccessTier::Full, true, true, CategoryGroup::Current],
        'honourary' => [AccessTier::Full, true, true, CategoryGroup::Current],
        'loa' => [AccessTier::Full, true, false, CategoryGroup::Current],
        'withdrawn' => [AccessTier::None, false, false, CategoryGroup::Resigned],
        'resigned' => [AccessTier::None, false, false, CategoryGroup::Resigned],
        'deceased' => [AccessTier::None, false, false, CategoryGroup::Deceased],
        'donor' => [AccessTier::None, false, false, CategoryGroup::NonMember],
        'guest' => [AccessTier::None, false, false, CategoryGroup::NonMember],
        'volunteer' => [AccessTier::None, false, false, CategoryGroup::NonMember],
        'admin' => [AccessTier::None, false, false, CategoryGroup::Admin],
        'security' => [AccessTier::None, false, false, CategoryGroup::Security],
    ];
}

it('has a table row for every Category', function () {
    expect(array_keys(categoryTable()))
        ->toEqualCanonicalizing(array_column(Category::cases(), 'value'));
});

it('resolves an access tier, a Directory answer, a sign-up answer and a group', function (Category $category) {
    [$tier, $listed, $signsUp, $group] = categoryTable()[$category->value];

    expect($category->accessTier())->toBe($tier)
        ->and($category->grantsDirectoryListing())->toBe($listed)
        ->and($category->canSignUp())->toBe($signsUp)
        ->and($category->group())->toBe($group);
})->with(Category::cases());
