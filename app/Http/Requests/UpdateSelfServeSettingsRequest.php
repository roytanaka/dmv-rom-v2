<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing a Group's self-serve settings (#582, spec #576, ADR-0026 §1 and §2) — self-serve shifts
 * on/off and the unit length in minutes, the pair the self-serve create flow reads to decide who
 * may author their own Shift and how long a unit runs. The mutation is authorized structurally
 * here: `authorize()` resolves the route-bound Group and delegates to the SchedulePolicy's
 * `updateSelfServe` gate (a Scheduler or Chair of the Group, plus the super-tier, only while
 * scheduling is on), never returning `true` blindly — so turning self-serve on for a Group that
 * runs no scheduling is refused. The controller passes `validated()`, never `all()`.
 */
class UpdateSelfServeSettingsRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy. The ability is reached on the Group, so the policy
     * is resolved by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateSelfServe', [Schedule::class, $this->route('group')]);
    }

    /**
     * Both settings are always submitted as a pair. The unit length is a whole number of minutes,
     * floored at 15 (legacy's finest picker step) and capped at 240 (four hours) to keep a
     * fat-fingered value from stretching one unit past a shift.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'self_serve_shifts' => ['required', 'boolean'],
            'self_serve_unit_minutes' => ['required', 'integer', 'min:15', 'max:240'],
        ];
    }
}
