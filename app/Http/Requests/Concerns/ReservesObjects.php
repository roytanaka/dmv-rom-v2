<?php

namespace App\Http\Requests\Concerns;

use App\Models\Group;
use App\Models\Shift;
use App\Support\Scheduling\ObjectClash;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * The Objects a Sign-up reserves, shared by the four write seams that carry them (#586, ADR-0026
 * §3) — a self-serve store and update, a take, and an officer placement. Each seam whitelists an
 * `objects` array and runs the one double-booking block; this trait is the single home of both,
 * so the rule reads the same everywhere and the clash check is not copied four times.
 *
 * When the Group has at least one active Object the field is **required** — legacy's desk refuses
 * to submit without one; a Group with no Objects never sends it and the field is optional and
 * absent (ADR-0026 §3). Every id must be an active Object of the Group, so a retired or foreign
 * Object is refused on write.
 */
trait ReservesObjects
{
    /**
     * The `objects` field rules for the given Group. Required with at least one when the Group
     * has any active Object; optional otherwise. Each id must be an active Object of the Group.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function objectRules(Group $group): array
    {
        $hasActiveObjects = $group->objects()->active()->exists();

        return [
            'objects' => $hasActiveObjects ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'objects.*' => [
                'integer',
                Rule::exists('objects', 'id')
                    ->where('group_id', $group->getKey())
                    ->where('active', true),
            ],
        ];
    }

    /**
     * Add a clash error on `objects` for each candidate Object another Sign-up holds at an
     * overlapping time ({@see ObjectClash}). The message names the Object and the other Shift's
     * date and time so the Member can pick something else without guessing. Skipped when the field
     * rules already failed — a retired or absent Object is that error's to report, not this one's.
     */
    protected function addObjectClashErrors(Validator $validator, Shift $candidate, ?int $excludeSignUpId = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $objectIds = array_map('intval', (array) $this->input('objects', []));

        foreach (ObjectClash::against($candidate, $objectIds, $excludeSignUpId) as $clash) {
            $validator->errors()->add('objects', trans('group.scheduling_panel.objects.clash', [
                'object' => $clash['object']->name,
                'when' => CarbonImmutable::instance($clash['shift']->starts_at)
                    ->setTimezone(config('app.org_timezone'))
                    ->locale(app()->getLocale())
                    ->isoFormat('D MMM, HH:mm'),
            ]));
        }
    }

    /**
     * Add the station clash warning on `shift_kind_id` when another seat overlaps the candidate's
     * station ({@see ObjectClash::station}) — the self-serve store and update seams only. A soft
     * warning the Member may wave through: `acknowledge_station_clash` skips it, so the resubmit
     * with the acknowledgement lands the Shift (ADR-0026 §5). Skipped when a field rule already
     * failed — a wrong kind is that error's to report — so the distinct key stands alone and the
     * dialog can offer the confirm on it.
     */
    protected function addStationClashError(Validator $validator, Shift $candidate, ?int $excludeShiftId = null): void
    {
        if ($this->boolean('acknowledge_station_clash') || $validator->errors()->isNotEmpty()) {
            return;
        }

        if (ObjectClash::station($candidate, $excludeShiftId)) {
            $validator->errors()->add('shift_kind_id', trans('group.scheduling_panel.self_serve.station_clash'));
        }
    }
}
