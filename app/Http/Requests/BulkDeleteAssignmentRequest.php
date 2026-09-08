<?php

namespace App\Http\Requests;

use App\Http\Controllers\AssignmentController;
use App\Models\SignUp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk-removing a Member's Sign-ups on the same filter that placed them (#363, PRD #352,
 * ADR-0021 §5) — the symmetric undo of {@see BulkStoreAssignmentRequest}, so a placed
 * regular who stops coming is not stranded. The whitelist mirrors the place filter:
 * re-submitting it selects the seats to take back out.
 *
 * Authorization is the schedule-admin gate on the route-bound Schedule
 * ({@see SignUpPolicy::removeAny}) — and *only* that. Removal is never held to the sign-up
 * floors: by the time a regular is being cleared they are exactly the person a floor would
 * bar. The per-row work is a plain delete of a seat the Member holds
 * ({@see AssignmentController::bulkDestroy}).
 */
class BulkDeleteAssignmentRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must administer scheduling for the
     * route-bound Schedule's Group (a Scheduler / Chair / super-tier, scheduling on).
     */
    public function authorize(): bool
    {
        return $this->user()->can('removeAny', [SignUp::class, $this->route('schedule')]);
    }

    /**
     * The same whitelist as bulk-place — the Member, the wall-clock times, days of week, the
     * date range, and the interval that together name the seats to remove.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['required', 'date_format:H:i', 'after:starts_time'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'interval' => ['required', Rule::in(['weekly', 'biweekly'])],
            'anchor_date' => ['required', 'date'],
        ];
    }
}
