<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReservesObjects;
use App\Models\Shift;
use App\Rules\OnMinuteGrid;
use App\Support\OrgTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Member changing their own self-authored Shift (#585, ADR-0026 §1) — station, start, or
 * units, until it starts. It accepts the same three fields as {@see StoreSelfServeShiftRequest}
 * and derives `ends_at` the same way; the unit count is never stored. Authorization is
 * structural — `authorize()` delegates to the ShiftPolicy's `manageSelfServe`, which resolves
 * the derived-ownership rule (self-serve Group, capacity 1, the actor's own single seat, not
 * yet started). The controller writes only the derived Shift fields; the Sign-up is untouched.
 */
class UpdateSelfServeShiftRequest extends FormRequest
{
    use ReservesObjects;

    /**
     * Authorize against the ShiftPolicy: the actor owns the route-bound Shift by the derived
     * rule and it has not started.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageSelfServe', $this->route('shift'));
    }

    /**
     * Load the Shift's Schedule and Group once (the range rule reads them) and read `starts_at`
     * on the org wall clock, exactly as the store request does.
     */
    protected function prepareForValidation(): void
    {
        $this->route('shift')->loadMissing('schedule.group');

        if ($this->has('starts_at')) {
            $this->merge(['starts_at' => OrgTime::toUtc($this->input('starts_at'))]);
        }
    }

    /**
     * The same whitelist as the store request: an active station kind of the Group, a start on
     * the 15-minute grid on or after the start of today inside the Schedule's range, and 1 to 8
     * units. A change resubmits all three, so each is required.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shift = $this->route('shift');
        $schedule = $shift->schedule;

        return [
            'shift_kind_id' => [
                'required',
                Rule::exists('shift_kinds', 'id')
                    ->where('group_id', $schedule->group_id)
                    ->where('active', true),
            ],
            'starts_at' => ['required', 'date', new OnMinuteGrid(15), $this->notBeforeToday(), $this->withinRange()],
            'units' => ['required', 'integer', 'min:1', 'max:'.Shift::SELF_SERVE_MAX_UNITS],
            // The Member waving through the station clash warning (ADR-0026 §5) — optional, true on
            // the resubmit from the confirm dialog.
            'acknowledge_station_clash' => ['sometimes', 'boolean'],
            ...$this->objectRules($schedule->group),
        ];
    }

    /**
     * The two overlap checks (ADR-0026 §3, §5): the Object double-booking block and the station
     * clash warning, both on this Shift's *new* derived interval and kind. The actor's own seat is
     * excluded from the Object block, and the Shift itself from the station warning — keeping the
     * same Object, or editing the units of one's own seat, is not a clash with oneself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startsAt = $this->date('starts_at');

            if ($startsAt === null) {
                return;
            }

            $shift = $this->route('shift');
            $group = $shift->schedule->group;
            $units = max(1, (int) $this->input('units'));

            $candidate = new Shift([
                'starts_at' => $startsAt,
                'ends_at' => Shift::deriveEndsAt($startsAt, $units, $group->self_serve_unit_minutes),
                'shift_kind_id' => $this->input('shift_kind_id'),
            ]);

            $ownSignUp = $shift->signUps()->where('member_id', $this->user()->getKey())->first();

            $this->addObjectClashErrors($validator, $candidate, $ownSignUp?->getKey());
            $this->addStationClashError($validator, $candidate, $shift->getKey());
        });
    }

    /**
     * A start no earlier than the start of today on the org wall clock (ADR-0026 §6).
     */
    private function notBeforeToday(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $startsAt = $this->date('starts_at');

            if ($startsAt !== null && $startsAt->lessThan(OrgTime::now()->startOfDay())) {
                $fail('group.scheduling_panel.self_serve.start_before_today')->translate();
            }
        };
    }

    /**
     * The derived interval must sit inside the Schedule's date range, computed from the units
     * and the Group's per-unit length exactly as the controller stores it.
     */
    private function withinRange(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $schedule = $this->route('shift')->schedule;
            $startsAt = $this->date('starts_at');

            if ($startsAt === null) {
                return;
            }

            $units = max(1, (int) $this->input('units'));
            $endsAt = Shift::deriveEndsAt($startsAt, $units, $schedule->group->self_serve_unit_minutes);

            if (! $schedule->coversInterval($startsAt, $endsAt)) {
                $fail('group.scheduling_panel.shift_outside_range')->translate();
            }
        };
    }
}
