<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a Schedule to a Group (#354, PRD #352, ADR-0021 §1). The mutation is
 * authorized structurally here — `authorize()` resolves the route-bound Group and
 * delegates to the SchedulePolicy, never returning `true` blindly — and `rules()` is a
 * whitelist of exactly the Schedule's authored fields. The controller passes
 * `validated()`, never `all()`.
 *
 * A new Schedule always starts as a `draft`, so `state` is not in the whitelist: it is
 * fixed at the database default, not chosen at creation. Publication is a later
 * `state` transition on the edit path ({@see UpdateScheduleRequest}).
 */
class StoreScheduleRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy: the actor must be able to add a Schedule
     * to the route-bound Group (a Scheduler / Chair / super-tier, scheduling on).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Schedule::class, $this->route('group')]);
    }

    /**
     * The whitelist of fields a Schedule may set at creation. `name` and `description`
     * are officer-authored content, stored as-authored (ADR-0004); `name` is typed by
     * the Scheduler with nothing pre-filled. The date range is required both ends, and
     * `ends_on` may not precede `starts_on`. `state` is deliberately absent — a new
     * Schedule is a draft.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
