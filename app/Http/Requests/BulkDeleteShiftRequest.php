<?php

namespace App\Http\Requests;

use App\Http\Controllers\ShiftController;
use App\Models\Shift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk-deleting Shifts on the same filter that created them (#362, PRD #352, ADR-0021 §2)
 * — legacy's skip-dates job without a field. The whitelist mirrors
 * {@see BulkStoreShiftRequest}: re-submitting the create filter selects the rows to take
 * back out.
 *
 * Authorization is the schedule-admin gate on the route-bound Schedule
 * ({@see ShiftPolicy::deleteAny}); the per-row zero-Sign-ups rule is *not* an authority
 * question and is not enforced here — a Shift with Members on it is skipped and reported,
 * and the batch deletes the rest ({@see ShiftController::bulkDestroy}).
 */
class BulkDeleteShiftRequest extends FormRequest
{
    /**
     * Authorize against the ShiftPolicy: the actor must administer scheduling for the
     * route-bound Schedule's Group (a Scheduler / Chair / super-tier, scheduling on).
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [Shift::class, $this->route('schedule')]);
    }

    /**
     * The same whitelist as bulk-create — start / end time, capacity, kind, days of week,
     * and the date range that together name the rows to remove.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schedule = $this->route('schedule');

        return [
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['required', 'date_format:H:i', 'after:starts_time'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'shift_kind_id' => [
                'nullable',
                Rule::exists('shift_kinds', 'id')->where('group_id', $schedule->group_id),
            ],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ];
    }
}
