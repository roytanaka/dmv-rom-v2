<?php

namespace App\Http\Requests;

use App\Enums\ScheduleState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a Schedule (#354, PRD #352, ADR-0021 §1) — its authored fields and its
 * publication `state`. Publishing and un-publishing are `state` transitions on this
 * same edit path, each with its own guard: publishing is purely additive (the
 * schedule-admin gate), while un-publishing is permitted only at zero Sign-ups
 * (ADR-0021 §1). So `authorize()` picks the ability by the transition the payload
 * requests, all three delegating to the SchedulePolicy. The controller passes
 * `validated()`, never `all()`.
 *
 * Every field is `sometimes`, so a field edit and a bare publish/un-publish share the
 * one route: a quick publish sends only `state`, an edit sends the authored fields.
 */
class UpdateScheduleRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy. A `state` transition routes to its own
     * ability — `publish` for draft → published, `unpublish` for published → draft —
     * and any other edit routes to `update`. The route-bound Schedule carries its
     * current state, so the requested transition is the target `state` against it.
     */
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');
        $user = $this->user();
        $target = $this->input('state');

        if ($target === ScheduleState::Published->value && $schedule->state === ScheduleState::Draft) {
            return $user->can('publish', $schedule);
        }

        if ($target === ScheduleState::Draft->value && $schedule->state === ScheduleState::Published) {
            return $user->can('unpublish', $schedule);
        }

        return $user->can('update', $schedule);
    }

    /**
     * The whitelist of editable fields — the authored fields plus the publication
     * `state`. Each is `sometimes` so a partial payload is valid: a bare publish sends
     * only `state`, an edit sends the fields it changes. When present, `name` and the
     * date range keep the same shape as creation, and `state` must be one of the two
     * lifecycle values.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'ends_on' => ['sometimes', 'required', 'date', 'after_or_equal:starts_on'],
            'state' => ['sometimes', Rule::enum(ScheduleState::class)],
        ];
    }

    /**
     * Enforce the range against its contents (#356, ADR-0021 §2): shrinking a Schedule's
     * date range is blocked while Shifts sit outside it, so the range and its contents can
     * never disagree. Only the boundary the payload actually moves is checked — a Shift
     * before the new `starts_on` fails on `starts_on`, one after the new `ends_on` fails
     * on `ends_on`. Runs only once the basic rules have passed, so the candidate dates are
     * known to parse. Resolved in PHP so the comparison never depends on the DB engine.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $movesStart = $this->has('starts_on');
            $movesEnd = $this->has('ends_on');

            if (! $movesStart && ! $movesEnd) {
                return;
            }

            $schedule = $this->route('schedule');
            $rangeStart = ($this->date('starts_on') ?? $schedule->starts_on)->copy()->startOfDay();
            $rangeEnd = ($this->date('ends_on') ?? $schedule->ends_on)->copy()->endOfDay();
            $conflict = 'group.scheduling_panel.schedule_range_conflict';

            foreach ($schedule->shifts as $shift) {
                if ($movesStart && $shift->starts_at->lessThan($rangeStart) && ! $validator->errors()->has('starts_on')) {
                    $validator->errors()->add('starts_on', trans($conflict));
                }

                if ($movesEnd && $shift->ends_at->greaterThan($rangeEnd) && ! $validator->errors()->has('ends_on')) {
                    $validator->errors()->add('ends_on', trans($conflict));
                }
            }
        });
    }
}
