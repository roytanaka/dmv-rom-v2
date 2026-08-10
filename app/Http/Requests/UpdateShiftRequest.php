<?php

namespace App\Http\Requests;

use App\Enums\ShiftAudience;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a Shift (#356, PRD #352, ADR-0021 §2) — its times, capacity, kind or audience.
 * Authorization is structural here, delegating to the ShiftPolicy (the schedule-admin
 * gate on the owning Group). The controller passes `validated()`, never `all()`.
 *
 * Every field is `sometimes`, so a partial payload is valid: raising capacity sends only
 * `capacity`, and it is a single update that disturbs nothing else. When either time is
 * touched, {@see prepareForValidation} backfills the other from the stored Shift so the
 * ordering and Schedule-range rules always see a complete pair — an edit can never move a
 * Shift outside its Schedule. Lowering capacity below the current Sign-up count is blocked
 * once Sign-ups exist (#357).
 */
class UpdateShiftRequest extends FormRequest
{
    /**
     * Authorize against the ShiftPolicy: the actor must be able to edit the route-bound
     * Shift (a schedule admin of its owning Group).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shift'));
    }

    /**
     * Backfill the untouched endpoint of the time pair from the stored Shift whenever
     * either endpoint is edited, so the ordering (`after`) and range rules below always
     * validate a complete pair — a partial time edit is still checked against the fixed
     * endpoint it did not send.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('starts_at') && ! $this->has('ends_at')) {
            return;
        }

        $shift = $this->route('shift');

        $this->merge([
            'starts_at' => $this->input('starts_at', $shift->starts_at->toDateTimeString()),
            'ends_at' => $this->input('ends_at', $shift->ends_at->toDateTimeString()),
        ]);
    }

    /**
     * The whitelist of editable fields. Each is `sometimes` so a partial payload is
     * valid. When the times are present (either edited directly or backfilled), `ends_at`
     * must fall after `starts_at` and the pair must sit inside the Schedule's date range
     * ({@see withinRange}). `shift_kind_id`, when present, must still name one of the
     * owning Group's kinds.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $groupId = $this->route('shift')->schedule->group_id;

        return [
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at', $this->withinRange()],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'shift_kind_id' => [
                'sometimes',
                'nullable',
                Rule::exists('shift_kinds', 'id')->where('group_id', $groupId),
            ],
            'audience' => ['sometimes', Rule::enum(ShiftAudience::class)],
        ];
    }

    /**
     * A closure rule rejecting a Shift whose instants fall outside its Schedule's date
     * range. Resolved in PHP against the route-bound Shift's Schedule so the comparison
     * never depends on the database engine.
     */
    private function withinRange(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $schedule = $this->route('shift')->schedule;
            $startsAt = $this->date('starts_at');

            if ($startsAt === null || ! $schedule->coversInterval($startsAt, $this->date('ends_at'))) {
                $fail('group.scheduling_panel.shift_outside_range')->translate();
            }
        };
    }
}
