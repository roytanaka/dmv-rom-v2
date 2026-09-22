<?php

namespace App\Http\Requests;

use App\Models\Shift;
use App\Support\OrgTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Member changing their own self-authored Shift (#585, ADR-0026 §1) — station, start, or
 * units, until it starts. It accepts the same three fields as {@see StoreSelfServeShiftRequest}
 * and derives `ends_at` the same way; the unit count is never stored. Authorization is
 * structural — `authorize()` delegates to the ShiftPolicy's `manageSelfServe`, which resolves
 * the derived-ownership rule (self-serve Group, capacity 1, the actor's own single seat, not
 * yet started). The controller writes only the derived Shift fields; the Sign-up is untouched.
 */
class UpdateSelfServeShiftRequest extends FormRequest
{
    /**
     * Authorize against the ShiftPolicy: the actor owns the route-bound Shift by the derived
     * rule and it has not started.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageSelfServe', $this->route('shift'));
    }

    /**
     * Load the Shift's Schedule and Group once (the range rule reads them) and read `starts_at`
     * on the org wall clock, exactly as the store request does.
     */
    protected function prepareForValidation(): void
    {
        $this->route('shift')->loadMissing('schedule.group');

        if ($this->has('starts_at')) {
            $this->merge(['starts_at' => OrgTime::toUtc($this->input('starts_at'))]);
        }
    }

    /**
     * The same whitelist as the store request: an active station kind of the Group, a start on
     * the 15-minute grid on or after the start of today inside the Schedule's range, and 1 to 8
     * units. A change resubmits all three, so each is required.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shift = $this->route('shift');
        $schedule = $shift->schedule;

        return [
            'shift_kind_id' => [
                'required',
                Rule::exists('shift_kinds', 'id')
                    ->where('group_id', $schedule->group_id)
                    ->where('active', true),
            ],
            'starts_at' => ['required', 'date', $this->onGrid(), $this->notBeforeToday(), $this->withinRange()],
            'units' => ['required', 'integer', 'min:1', 'max:'.Shift::SELF_SERVE_MAX_UNITS],
        ];
    }

    /**
     * A start on the quarter-hour grid (ADR-0026 §2). Offsets are whole hours, so the check
     * reads the stored UTC instant's minute-of-hour directly.
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
     * A start no earlier than the start of today on the org wall clock (ADR-0026 §6).
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
     * The derived interval must sit inside the Schedule's date range, computed from the units
     * and the Group's per-unit length exactly as the controller stores it.
     */
    private function withinRange(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $schedule = $this->route('shift')->schedule;
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
