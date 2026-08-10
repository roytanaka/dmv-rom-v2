<?php

namespace App\Http\Requests;

use App\Enums\ShiftAudience;
use App\Models\Shift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a Shift to a Schedule (#356, PRD #352, ADR-0021 §2). The mutation is authorized
 * structurally here — `authorize()` resolves the route-bound Schedule and delegates to
 * the ShiftPolicy, never returning `true` blindly — and `rules()` is a whitelist of
 * exactly the Shift's authored fields. The controller passes `validated()`, never
 * `all()`.
 *
 * The date range is enforced at authoring time: a Shift whose times fall outside its
 * Schedule's `[starts_on, ends_on]` is rejected, so the range and its contents can never
 * disagree (ADR-0021 §2). An optional `shift_kind_id` must name one of the owning Group's
 * own kinds. `capacity` and `audience` fall to the database defaults (1, `group`) when
 * absent — the permissive `open` case is always a deliberate choice.
 */
class StoreShiftRequest extends FormRequest
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
     * The whitelist of fields a Shift may set. `ends_at` is required and must fall after
     * `starts_at` — a Shift has a positive duration and duration is always derived from
     * the pair. Both instants must sit inside the Schedule's date range
     * ({@see Schedule::coversInterval}). `shift_kind_id` is optional and, when given, must
     * be one of the owning Group's kinds. `capacity` defaults to 1 and `audience` to
     * `group` when absent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schedule = $this->route('schedule');

        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', $this->withinRange()],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'shift_kind_id' => [
                'nullable',
                Rule::exists('shift_kinds', 'id')->where('group_id', $schedule->group_id),
            ],
            'audience' => ['sometimes', Rule::enum(ShiftAudience::class)],
        ];
    }

    /**
     * A closure rule rejecting a Shift whose instants fall outside the Schedule's date
     * range. Resolved in PHP against the route-bound Schedule so the comparison never
     * depends on the database engine.
     */
    private function withinRange(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $schedule = $this->route('schedule');
            $startsAt = $this->date('starts_at');

            if ($startsAt === null || ! $schedule->coversInterval($startsAt, $this->date('ends_at'))) {
                $fail('group.scheduling_panel.shift_outside_range')->translate();
            }
        };
    }
}
