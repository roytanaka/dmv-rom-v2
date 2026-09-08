<?php

use App\Enums\GroupLogo;

/*
 * The curated Group-logo set (PRD #253) and its shipped assets. A logo is a Group's
 * identity mark on the launcher: unlike a banner it has no per-Kind default, so the
 * only invariant to guard is that every enum case — plus the generic fallback — has
 * exactly one shipped asset file. Assets are format-mixed (SVG or raster), so we match
 * on the filename stem and let the extension vary. A dangling key would ship a broken
 * tile; this is the cheap guard that catches it.
 */

/** The one committed asset file for a logo stem, or null when none/ambiguous exists. */
function logoAssetFor(string $stem): ?string
{
    // Unit tests don't boot the app, so resolve the assets dir off the repo root
    // rather than via resource_path().
    $dir = dirname(__DIR__, 2).'/resources/images/groups/logos';
    $matches = glob("{$dir}/{$stem}.*") ?: [];

    return count($matches) === 1 ? $matches[0] : null;
}

it('ships exactly one asset file for every curated logo case', function (GroupLogo $logo) {
    expect(logoAssetFor($logo->value))->not->toBeNull();
})->with(GroupLogo::cases());

it('defines a shipped generic fallback mark', function () {
    expect(logoAssetFor(GroupLogo::Fallback))->not->toBeNull();
});
