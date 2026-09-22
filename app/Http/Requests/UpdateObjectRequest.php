<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming, retiring or reinstating an Object (#584, ADR-0026 §3). The mutation is authorized
 * structurally here: `authorize()` resolves the route-bound Object's Group and delegates to the
 * SchedulePolicy's `manageObjects` gate — so an admin of a different Group is refused, authority
 * never leaking across Groups.
 *
 * Every field is `sometimes`, so a partial payload is valid: a rename sends `name`, a retire
 * sends `active` false, a reinstate `active` true. The name stays unique within the Group,
 * ignoring this Object's own row so a no-op rename is not a self-collision.
 */
class UpdateObjectRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy on the route-bound Object's Group; the policy is
     * resolved by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageObjects', [Schedule::class, $this->route('object')->group]);
    }

    /**
     * The whitelist of editable fields. `name`, when sent, is required and unique among the
     * Group's other Objects; `active` is the retire / reinstate flag.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $object = $this->route('object');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('objects', 'name')
                    ->where('group_id', $object->group_id)
                    ->ignore($object->getKey()),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
