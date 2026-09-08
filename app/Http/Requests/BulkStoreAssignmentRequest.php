<?php

namespace App\Http\Requests;

use App\Http\Controllers\AssignmentController;
use App\Models\Member;
use App\Models\SignUp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk-placing a named Member across a Schedule's Shifts in one form run (#363, PRD #352,
 * ADR-0021 §5) — the batch form of {@see StoreAssignmentRequest}. It retires Reception's
 * fortnight, and like its Shift-authoring sibling {@see BulkStoreShiftRequest} it adds no
 * entity, no column, no stored pattern: the days of week, the date range, and the interval
 * all live in the form, never in a row.
 *
 * The whitelist is the filter that names the target Shifts — the Member being placed, the
 * days of week and wall-clock times, the date range, and the interval (weekly or biweekly)
 * from a chosen anchor date. Authorization is the same schedule-admin gate as a single
 * placement, resolved once for the whole run against the route-bound Schedule
 * ({@see SignUpPolicy::assignAny}); bulk is not a privileged path, and both sign-up floors
 * on the placed Member are constant across every row.
 *
 * Deliberately *not* validated here: capacity and the one-seat rule. Those are per-row
 * state, and a run that hits a full Shift or a seat the Member already holds writes the rest
 * and reports the skip ({@see AssignmentController::bulkStore}) — skip-and-report, never
 * all-or-nothing.
 */
class BulkStoreAssignmentRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must be able to bulk-place the requested
     * Member across the route-bound Schedule's Shifts — clearing the schedule-admin gate and
     * the placed Member's two floors. A `member_id` that names nobody fails the `exists` rule
     * below as a validation error rather than reaching the policy.
     */
    public function authorize(): bool
    {
        $member = Member::find($this->input('member_id'));

        if ($member === null) {
            return true;
        }

        return $this->user()->can('assignAny', [SignUp::class, $this->route('schedule'), $member]);
    }

    /**
     * The whitelist. `member_id` names the person placed. `starts_time` / `ends_time` are
     * wall-clock times (`H:i`) identifying the target Shifts; `days_of_week` is a non-empty
     * list of Carbon day numbers (0 = Sunday … 6 = Saturday). The date range is
     * `from_date`..`to_date`. `interval` is `weekly` (every matching week) or `biweekly`
     * (alternating weeks), phased from `anchor_date` — the chosen start date the interval
     * counts from.
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
