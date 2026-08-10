<?php

namespace App\Http\Requests;

use App\Enums\ScheduleState;
use Illuminate\Contracts\Validation\ValidationRule;
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
}
