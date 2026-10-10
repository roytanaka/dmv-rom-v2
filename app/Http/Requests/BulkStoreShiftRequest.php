<?php

namespace App\Http\Requests;

use App\Http\Controllers\ShiftController;
use App\Models\Shift;
use App\Rules\OnMinuteGrid;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Bulk-creating a month of Shifts in one form run (#362, PRD #352, ADR-0021 §2). A bulk
 * run is N single writes plus a report — it adds no entity, no column, no stored pattern:
 * the days of week and the date range live in the form, never in a row. There is no
 * interval option; a Shift lands on *every* matching day in the range.
 *
 * The whitelist is the shape of one Shift (capacity, optional kind) plus the fan-out
 * inputs: one or more start / end times (#734), days of week, and a date range. Every
 * time pair lands on every matching day. Authorization is the same
 * schedule-admin gate as a single add — {@see ShiftPolicy::create} on the route-bound
 * Schedule; bulk is not a privileged path.
 *
 * Deliberately *not* validated here: whether each generated day falls inside the
 * Schedule's date range. That is a per-row rule, and a run that overruns the range writes
 * the days that fit and reports the rest ({@see ShiftController::bulkStore})
 * — skip-and-report, never all-or-nothing.
 */
class BulkStoreShiftRequest extends FormRequest
{
    /**
     * Authorize against the ShiftPolicy: the actor must be able to add a Shift to the
     * route-bound Schedule (a Scheduler / Chair / super-tier of its Group, scheduling on).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Shift::class, $this->route('schedule')]);
    }

    /**
     * The whitelist. `times` is a non-empty list of `starts_time` / `ends_time` pairs,
     * wall-clock times (`H:i`) stamped onto every matching day; each pair's `ends_time` must
     * be after its `starts_time` so every generated Shift has a positive duration.
     * `days_of_week` is a non-empty list of Carbon day numbers (0 = Sunday … 6 = Saturday).
     * The date range is `from_date`..`to_date`. `capacity` defaults to 1 and
     * `shift_kind_id`, when given, must be one of the owning Group's kinds.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schedule = $this->route('schedule');

        return [
            'times' => ['required', 'array', 'min:1'],
            'times.*.starts_time' => ['required', 'date_format:H:i', new OnMinuteGrid],
            'times.*.ends_time' => ['required', 'date_format:H:i', 'after:times.*.starts_time', new OnMinuteGrid],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'shift_kind_id' => [
                'nullable',
                // Only an *active* kind may go on a bulk-created Shift, as for a single add: a
                // retired kind still labels its old Shifts but is off the picker (#567, ADR-0021 §3).
                Rule::exists('shift_kinds', 'id')
                    ->where('group_id', $schedule->group_id)
                    ->where('active', true),
            ],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ];
    }

    /**
     * Refuse a hand-made Shift on a month's group-tour Schedule (#795, ADR-0032 §4): its Shifts
     * come only from Bookings. A validation rule, not the policy, so it binds super-tier too.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('schedule')->isGroupTour()) {
                $validator->errors()->add('schedule', trans('group.bookings.group_tour_schedule_locked'));
            }
        });
    }
}
