<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming, retiring or reinstating a shift kind (#567, ADR-0021 §3). The mutation is
 * authorized structurally here: `authorize()` resolves the route-bound kind's Group and
 * delegates to the SchedulePolicy's `manageShiftKinds` gate — so an admin of a different Group
 * is refused, authority never leaking across Groups.
 *
 * Every field is `sometimes`, so a partial payload is valid: a rename sends `name`, a retire
 * sends `active` false, a reinstate `active` true. The name stays unique within the Group,
 * ignoring this kind's own row so a no-op rename is not a self-collision.
 */
class UpdateShiftKindRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy on the route-bound kind's Group; the policy is
     * resolved by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageShiftKinds', [Schedule::class, $this->route('shiftKind')->group]);
    }

    /**
     * The whitelist of editable fields. `name`, when sent, is required and unique among the
     * Group's other kinds; `active` is the retire / reinstate flag; `off_site` is the flag that
     * widens the Object hold to a day either side (#587, ADR-0026 §4).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kind = $this->route('shiftKind');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('shift_kinds', 'name')
                    ->where('group_id', $kind->group_id)
                    ->ignore($kind->getKey()),
            ],
            'active' => ['sometimes', 'boolean'],
            'off_site' => ['sometimes', 'boolean'],
        ];
    }
}
