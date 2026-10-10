<?php

namespace App\Http\Requests;

use App\Models\BookingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a Group's group-tour settings (#794, ADR-0032 §1, §4): the shift kind a Booking's Shift
 * takes (optional, one of the Group's own kinds) and the label its month's group-tour Schedule is
 * named from ("Group tours", "Visites de groupe"), always submitted together. The label is
 * content, never translated (ADR-0004). Authorized through the BookingTypePolicy's `manage` gate.
 */
class UpdateGroupTourSettingsRequest extends FormRequest
{
    /**
     * Authorize against the BookingTypePolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [BookingType::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_tour_shift_kind_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('shift_kinds', 'id')->where('group_id', $this->route('group')->getKey()),
            ],
            'group_tour_label' => ['required', 'string', 'max:255'],
        ];
    }
}
