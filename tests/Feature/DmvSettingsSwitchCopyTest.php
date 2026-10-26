<?php

use App\Models\Group;
use Illuminate\Support\Facades\Lang;

/*
 * The Group switches on the DMV Settings form (#854, PRD #836): the announcements capability
 * reads "News", and every capability and visitor-figure switch carries a one-line description
 * in both languages, so a new flag cannot ship without one.
 */

it('labels the announcements capability News', function (string $locale, string $label, string $letter) {
    expect(__('dmv_settings.capability.has_announcements', [], $locale))->toBe($label)
        ->and(__('dmv_settings.tree.letter.has_announcements', [], $locale))->toBe($letter);
})->with([
    'en' => ['en', 'News', 'N'],
    'fr' => ['fr', 'Nouvelles', 'N'],
]);

it('describes every Group switch in both languages', function (string $locale) {
    foreach (Group::CAPABILITY_FLAGS as $flag) {
        expect(Lang::has("dmv_settings.capability_help.{$flag}", $locale, false))->toBeTrue("{$locale} capability_help.{$flag}");
    }
    foreach (Group::COLLECTION_FLAGS as $flag) {
        expect(Lang::has("dmv_settings.collection_help.{$flag}", $locale, false))->toBeTrue("{$locale} collection_help.{$flag}");
    }
})->with(['en', 'fr']);
