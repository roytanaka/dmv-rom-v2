<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReservesObjects;
use App\Models\Shift;
use App\Support\OrgTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Member writing their own Shift in a self-serve Group (#585, ADR-0026 §1, §2). Distinct
 * from the Scheduler's {@see StoreShiftRequest}: the actor picks a station (`shift_kind_id`),
 * a start, and a count of **units**, and the controller derives `ends_at` and writes the
 * Shift plus their Sign-up in one transaction. Authorization is structural — `authorize()`
 * delegates to the ShiftPolicy's `createSelfServe` on the route-bound Schedule.
 *
 * The unit count is never stored (ADR-0026 §2); it lives only in this request, where the
 * controller reads it back to derive the end. The rules make the shape legacy has typed for
 * years unreachable when wrong: a station that is one of the Group's active kinds, a start on
 * the quarter-hour grid on or after the start of today (the desk case — an 11:00 written at
 * 11:10 is fine, yesterday is not) inside the Schedule's range, and 1 to 8 units.
 */
class StoreSelfServeShiftRequest extends FormRequest
{
    use ReservesObjects;

    /**
     * Authorize against the ShiftPolicy: the actor may write a self-serve Shift onto the
     * route-bound Schedule — its Group is self-serve, the Schedule is published and readable,
     * and the actor clears both sign-up floors.
     */
    public function authorize(): bool
    {
        return $this->user()->can('createSelfServe', [Shift::class, $this->route('schedule')]);
    }

    /**
     * Read `starts_at` as the organization's wall clock and hand the validator the equivalent
     * UTC instant, which is how it is stored ({@see OrgTime}). The client sends a bare
     * `Y-m-d\TH:i` with no offset, so without this the app timezone (UTC) would be assumed and
     * a 10am start at the museum would be stored — and grid-checked — as 5am.
     */
    protected function prepareForValidation(): void
    {
        // The policy and the range rule both read the Schedule's Group; load it once here so
        // neither lazy-loads under strict mode (the route binds the Schedule alone).
        $this->route('schedule')->loadMissing('group');

        if ($this->has('starts_at')) {
            $this->merge(['starts_at' => OrgTime::toUtc($this->input('starts_at'))]);
        }
    }

    /**
     * The whitelist: an active station kind of the Group, a start on the 15-minute grid, on or
     * after the start of today, inside the Schedule's range, and 1 to 8 units. `ends_at` is
     * derived, never sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schedule = $this->route('schedule');

        return [
            'shift_kind_id' => [
                'required',
                Rule::exists('shift_kinds', 'id')
                    ->where('group_id', $schedule->group_id)
                    ->where('active', true),
            ],
            'starts_at' => ['required', 'date', $this->onGrid(), $this->notBeforeToday(), $this->withinRange()],
            'units' => ['required', 'integer', 'min:1', 'max:'.Shift::SELF_SERVE_MAX_UNITS],
            ...$this->objectRules($schedule->group),
        ];
    }

    /**
     * The Object double-booking block (ADR-0026 §3): an Object another Sign-up holds at an
     * overlapping time is refused. The candidate is the Shift this store will write — its derived
     * interval and chosen kind — so the hold matches what will be stored. No Sign-up is excluded:
     * a store creates a new seat.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startsAt = $this->date('starts_at');

            if ($startsAt === null) {
                return;
            }

            $group = $this->route('schedule')->group;
            $units = max(1, (int) $this->input('units'));

            $candidate = new Shift([
                'starts_at' => $startsAt,
                'ends_at' => Shift::deriveEndsAt($startsAt, $units, $group->self_serve_unit_minutes),
                'shift_kind_id' => $this->input('shift_kind_id'),
            ]);

            $this->addObjectClashErrors($validator, $candidate);
        });
    }

    /**
     * A closure rule rejecting a start that is not on the quarter-hour grid (ADR-0026 §2). The
     * org zone offsets by whole hours, so the minute-of-hour is the same in UTC — the check
     * reads the stored instant directly.
     */
    private function onGrid(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $startsAt = $this->date('starts_at');

            if ($startsAt === null || $startsAt->minute % 15 !== 0 || $startsAt->second !== 0) {
                $fail('group.scheduling_panel.self_serve.start_off_grid')->translate();
            }
        };
    }

    /**
     * A closure rule rejecting a start on an earlier day (ADR-0026 §6). A start already passed
     * *today* is accepted — arriving at 11:10 and writing an 11:00 start is the whole desk case
     * — so the floor is the start of today on the org wall clock, not "now".
     */
    private function notBeforeToday(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $startsAt = $this->date('starts_at');

            if ($startsAt !== null && $startsAt->lessThan(OrgTime::now()->startOfDay())) {
                $fail('group.scheduling_panel.self_serve.start_before_today')->translate();
            }
        };
    }

    /**
     * A closure rule rejecting a Shift whose derived interval falls outside the Schedule's date
     * range ({@see Schedule::coversInterval}). The end is derived from the start, the units, and
     * the Group's per-unit length exactly as the controller derives it, so the range is checked
     * against the interval that will be stored. Resolved in PHP so it never depends on the DB
     * engine. Units off the 1-to-8 grid are clamped here only so the range check has a positive
     * span; the `units` rule reports the real error.
     */
    private function withinRange(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $schedule = $this->route('schedule');
            $startsAt = $this->date('starts_at');

            if ($startsAt === null) {
                return;
            }

            $units = max(1, (int) $this->input('units'));
            $endsAt = Shift::deriveEndsAt($startsAt, $units, $schedule->group->self_serve_unit_minutes);

            if (! $schedule->coversInterval($startsAt, $endsAt)) {
                $fail('group.scheduling_panel.shift_outside_range')->translate();
            }
        };
    }
}
