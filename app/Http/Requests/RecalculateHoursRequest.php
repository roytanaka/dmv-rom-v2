<?php

namespace App\Http\Requests;

use App\Models\HoursRecord;
use App\Support\OrgTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recalculating a Group's scheduled hours for a month (#410, PRD #406, ADR-0022 §2). The
 * mutation is authorized structurally here — `authorize()` resolves the route-bound Group and
 * delegates to the HoursRecordPolicy's `recalculate` gate (a Scheduler or Chair of a Group
 * that runs scheduling), never returning `true` blindly — and `rules()` is a whitelist of the
 * single field the action takes: which month.
 *
 * The month is bounded to the **current fiscal year** (§2). Legacy has no such bound, but the
 * fiscal-year report is what the ROM sees, and silently restating a closed year is the kind of
 * thing you learn about from someone else — so a month in a prior (or future) fiscal year is
 * refused rather than recalculated. The window is the twelve buckets {@see OrgTime} names for
 * the current fiscal year, so a prior-year month is simply not in the allowed set.
 */
class RecalculateHoursRequest extends FormRequest
{
    /**
     * Authorize against the HoursRecordPolicy: the actor must be a Scheduler or Chair of the
     * route-bound Group, and the Group must run scheduling.
     */
    public function authorize(): bool
    {
        return $this->user()->can('recalculate', [HoursRecord::class, $this->route('group')]);
    }

    /**
     * The whitelist: exactly the month to recalculate, constrained to the current fiscal
     * year's twelve buckets so a closed year cannot be restated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year_month' => [
                'required',
                'string',
                Rule::in(OrgTime::fiscalYearMonths(OrgTime::currentFiscalYear())),
            ],
        ];
    }

    /**
     * Say plainly that only the current fiscal year is open when a closed year is targeted,
     * so an officer understands the refusal rather than guessing at it (ADR-0022 §2).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year_month.in' => trans('hours.recalc.closed_year'),
        ];
    }
}
