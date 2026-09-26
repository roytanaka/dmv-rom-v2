<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Rejects a time that is not on the minute grid (#639, ADR-0028). Nobody schedules to the
 * minute: every Shift and Meeting time steps in fives, and self-serve starts in fifteens
 * (ADR-0026 §2). The pickers offer only grid steps; this is the server's copy of that rule.
 *
 * Takes any shape a time field carries: a bare `H:i`, the picker's `Y-m-d\TH:i`, or the UTC
 * instant a Form Request converts it to. The org zone offsets by whole hours, so the
 * minute-of-hour is the same in UTC. A value that does not parse passes here; the field's
 * `date` or `date_format` rule reports it.
 */
class OnMinuteGrid implements ValidationRule
{
    public function __construct(private int $minutes = 5) {}

    /**
     * Fail when the minute is not a multiple of the step, or the time carries seconds.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $time = CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return;
        }

        if ($time->minute % $this->minutes !== 0 || $time->second !== 0) {
            $fail('scheduling.time_off_grid')->translate(['minutes' => $this->minutes]);
        }
    }
}
