<?php

use App\Enums\AccessTier;
use App\Enums\Category;

it('resolves the access tier for every category', function (Category $category, AccessTier $tier) {
    expect($category->accessTier())->toBe($tier);
})->with([
    'Active is Full' => [Category::Active, AccessTier::Full],
    'Honourary is Full' => [Category::Honourary, AccessTier::Full],
    'Sustaining is Full' => [Category::Sustaining, AccessTier::Full],
    'LOA keeps Full view' => [Category::Loa, AccessTier::Full],
    'Provisional is Limited' => [Category::Provisional, AccessTier::Limited],
    'PreActive is Limited' => [Category::PreActive, AccessTier::Limited],
    'Resigned is None' => [Category::Resigned, AccessTier::None],
    'Withdrawn is None' => [Category::Withdrawn, AccessTier::None],
    'Deceased is None' => [Category::Deceased, AccessTier::None],
]);

it('resolves the sign-up floor for every category', function (Category $category, bool $canSignUp) {
    expect($category->canSignUp())->toBe($canSignUp);
})->with([
    'Active can sign up' => [Category::Active, true],
    'Honourary can sign up' => [Category::Honourary, true],
    'Sustaining can sign up' => [Category::Sustaining, true],
    'Provisional can sign up' => [Category::Provisional, true],
    'PreActive can sign up' => [Category::PreActive, true],
    'LOA cannot sign up' => [Category::Loa, false],
    'Resigned cannot sign up' => [Category::Resigned, false],
    'Withdrawn cannot sign up' => [Category::Withdrawn, false],
    'Deceased cannot sign up' => [Category::Deceased, false],
]);

it('grants LOA full view but no sign-up', function () {
    expect(Category::Loa->accessTier())->toBe(AccessTier::Full)
        ->and(Category::Loa->canSignUp())->toBeFalse();
});
