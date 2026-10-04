<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a Group's empty-desk settings (#487, spec #479, ADR-0024 §7) — the alert on/off, the
 * look-ahead days, and which of the Group's shift kinds to watch, the trio the daily pass reads
 * to decide which unstaffed Shifts earn an alert. The mutation is authorized structurally here:
 * `authorize()` resolves the route-bound Group and delegates to the SchedulePolicy's
 * `updateEmptyDeskAlert` gate (a Scheduler or Chair of the Group, plus the super-tier, only while
 * scheduling is on), never returning `true` blindly. The controller passes `validated()`.
 */
class UpdateEmptyDeskSettingsRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy. The ability is reached on the Group, so the policy
     * is resolved by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateEmptyDeskAlert', [Schedule::class, $this->route('group')]);
    }

    /**
     * The alert switch and look-ahead are always submitted as a pair; the watched-kinds list may
     * be empty (the Group watches nothing). The look-ahead is a whole count of at least one day
     * and capped well above the department's 3 to keep a fat-fingered value from scanning weeks
     * ahead. Each watched id must be a shift kind of *this* Group — the server never trusts the
     * browser to name a kind the Group does not own.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'empty_desk_alert_enabled' => ['required', 'boolean'],
            'empty_desk_days_ahead' => ['required', 'integer', 'min:1', 'max:90'],
            'watched_shift_kinds' => ['present', 'array'],
            'watched_shift_kinds.*' => [
                'integer',
                Rule::exists('shift_kinds', 'id')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
