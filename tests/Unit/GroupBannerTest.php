<?php

use App\Enums\GroupBanner;
use App\Enums\Kind;

/*
 * The curated banner set (#191) and its per-Kind default — the mapping the
 * migration backfills existing Groups with so each reads with intent.
 */

it('resolves a curated default banner for every Kind', function (Kind $kind) {
    expect(GroupBanner::defaultFor($kind))->toBeInstanceOf(GroupBanner::class);
})->with(Kind::cases());

it('maps each Kind to a distinct default banner', function () {
    $defaults = array_map(fn (Kind $kind) => GroupBanner::defaultFor($kind), Kind::cases());

    expect($defaults)->toHaveCount(count(array_unique($defaults, SORT_REGULAR)));
});
