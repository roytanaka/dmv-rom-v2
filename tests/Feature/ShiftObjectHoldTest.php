<?php

use App\Models\Shift;
use App\Models\ShiftKind;
use Illuminate\Support\Carbon;

/*
 * The Object hold a Shift computes (#586, #587, ADR-0026 §3, §4). An ordinary Shift holds its
 * Objects for its own `[starts_at, ends_at]`; a Shift whose kind is off-site widens the hold to
 * the start of the day before and the end of the day after, on the org wall clock. The window is
 * read here in one place by the double-booking block, so this is where both boundaries are pinned.
 */

/** A Shift at the given museum wall-clock times, its kind relation set (or none). */
function holdShift(string $start, string $end, ?ShiftKind $kind = null): Shift
{
    $shift = new Shift([
        'starts_at' => Carbon::parse($start, config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse($end, config('app.org_timezone'))->utc(),
    ]);
    $shift->setRelation('kind', $kind);

    return $shift;
}

it('holds an ordinary Shift for its own interval', function () {
    $hold = holdShift('2026-06-16 10:00', '2026-06-16 12:15', new ShiftKind(['off_site' => false]))->objectHold();

    expect($hold->start->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-16 10:00:00')
        ->and($hold->end->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-16 12:15:00');
});

it('holds an Object for a kind-less Shift over its own interval', function () {
    $hold = holdShift('2026-06-16 10:00', '2026-06-16 12:15')->objectHold();

    expect($hold->start->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-16 10:00:00')
        ->and($hold->end->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-16 12:15:00');
});

it('widens an off-site Shift to the day before through the day after', function () {
    $hold = holdShift('2026-06-16 10:00', '2026-06-16 12:15', new ShiftKind(['off_site' => true]))->objectHold();

    // Start of the day before 16 June through the end of the day after.
    expect($hold->start->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-15 00:00:00')
        ->and($hold->end->setTimezone(config('app.org_timezone'))->toDateTimeString())->toBe('2026-06-17 23:59:59');
});
