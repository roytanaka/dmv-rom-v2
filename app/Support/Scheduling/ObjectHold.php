<?php

namespace App\Support\Scheduling;

use App\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * The window an Object counts as in use (#586, ADR-0026 §3, §4) — the span the double-booking
 * block reserves it for. Computed in one place, {@see Shift::objectHold()}: for an
 * ordinary Shift it is the Shift's own `[starts_at, ends_at]`; an off-site kind widens it a day
 * each side (#587), which is a single branch there. Two Objects clash only when their holds
 * overlap.
 *
 * The overlap is **half-open** on the end: two holds that only touch at an endpoint do not
 * overlap, so a 10:00–11:00 hold and an 11:00–12:00 hold are back-to-back, not a clash
 * (ADR-0026 §3).
 */
final class ObjectHold
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /**
     * Whether this hold overlaps another. Strict on both ends — touching ends do not overlap —
     * so back-to-back reservations of the same Object are allowed.
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }
}
