<?php

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\DepartureReason;
use Illuminate\Support\Facades\Lang;

/*
 * Every value the Records office shows has a label in both languages (#916, ADR-0038
 * §4), so a new case cannot reach the page as a raw lang key.
 */

it('labels every Category in both languages', function (Category $category, string $locale) {
    expect(Lang::hasForLocale('member.standing.'.$category->value, $locale))->toBeTrue();
})->with(Category::cases())->with(['en', 'fr']);

it('labels every Category group in both languages', function (CategoryGroup $group, string $locale) {
    expect(Lang::hasForLocale('member.category_group.'.$group->value, $locale))->toBeTrue();
})->with(CategoryGroup::cases())->with(['en', 'fr']);

it('labels every departure reason in both languages', function (DepartureReason $reason, string $locale) {
    expect(Lang::hasForLocale('member.departure_reason.'.$reason->value, $locale))->toBeTrue();
})->with(DepartureReason::cases())->with(['en', 'fr']);

it('holds the ten departure reasons the office records', function () {
    expect(array_column(DepartureReason::cases(), 'value'))->toEqualCanonicalizing([
        'family', 'job', 'health', 'moved', 'none_given',
        'age', 'schedule', 'stopped', 'other', 'involuntary',
    ]);
});
