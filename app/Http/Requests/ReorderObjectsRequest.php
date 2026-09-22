<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reordering a Group's Objects (#584, ADR-0026 §3) — the id list, in the order the picker should
 * offer them. The mutation is authorized structurally here: `authorize()` resolves the
 * route-bound Group and delegates to the SchedulePolicy's `manageObjects` gate.
 *
 * Each id must name an Object of *this* Group — the server never trusts the browser to reorder an
 * Object the Group does not own — so a foreign id is rejected before the write.
 */
class ReorderObjectsRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy on the route-bound Group; the policy is resolved by the
     * class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageObjects', [Schedule::class, $this->route('group')]);
    }

    /**
     * The id list is present and each entry is an Object of this Group.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['present', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('objects', 'id')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
