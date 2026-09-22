<?php

use App\Support\Scheduling\ObjectHold;
use Carbon\CarbonImmutable;

/*
 * The Object hold window (#586, ADR-0026 §3, §4) — the span an Object counts as in use. For an
 * ordinary Shift it is the Shift's own [starts_at, ends_at]; the overlap test is half-open, so
 * two holds that only touch at an endpoint do not overlap (back-to-back is free).
 */

function holdAt(string $start, string $end): ObjectHold
{
    return new ObjectHold(CarbonImmutable::parse($start), CarbonImmutable::parse($end));
}

it('overlaps a hold whose span it crosses', function () {
    expect(holdAt('2026-06-16 10:00', '2026-06-16 12:00')->overlaps(holdAt('2026-06-16 11:00', '2026-06-16 13:00')))->toBeTrue();
});

it('does not overlap a hold that only touches its end', function () {
    // 10:00–11:00 then 11:00–12:00: back-to-back, touching ends, not a clash.
    expect(holdAt('2026-06-16 10:00', '2026-06-16 11:00')->overlaps(holdAt('2026-06-16 11:00', '2026-06-16 12:00')))->toBeFalse();
});

it('does not overlap a hold that ends before it starts', function () {
    expect(holdAt('2026-06-16 13:00', '2026-06-16 14:00')->overlaps(holdAt('2026-06-16 10:00', '2026-06-16 11:00')))->toBeFalse();
});

it('is symmetric', function () {
    $a = holdAt('2026-06-16 10:00', '2026-06-16 12:00');
    $b = holdAt('2026-06-16 11:00', '2026-06-16 13:00');

    expect($a->overlaps($b))->toBe($b->overlaps($a));
});
