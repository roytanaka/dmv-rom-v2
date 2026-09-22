<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a shift kind to a Group (#567, ADR-0021 §3) — name only. The mutation is authorized
 * structurally here: `authorize()` resolves the route-bound Group and delegates to the
 * SchedulePolicy's `manageShiftKinds` gate (a Scheduler or Chair of the Group, plus the
 * super-tier, only while scheduling is on), never returning `true` blindly. The controller
 * appends the new kind to the end of the Group's sort order and marks it active.
 *
 * The name is officer-authored content, stored single-column and as-authored — never
 * translated (ADR-0004). It must be unique within the Group; the same name is free in a
 * different Group, so the uniqueness is scoped to `group_id`.
 */
class StoreShiftKindRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy on the route-bound Group; the policy is resolved
     * by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageShiftKinds', [Schedule::class, $this->route('group')]);
    }

    /**
     * The whitelist is the kind's one authored field. The name is required and unique among
     * *this* Group's kinds — a duplicate within the Group is rejected, the same name in a
     * different Group is allowed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shift_kinds', 'name')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
