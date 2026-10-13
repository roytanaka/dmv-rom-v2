<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Choosing the shift kinds a Tour maps to (#788, ADR-0033 §1) — the full set of kind ids, which
 * replaces the Tour's mapping. An empty list clears it. Authorized through the TourPolicy's
 * `manage` gate; each id must name a shift kind of the Tour's own Group.
 */
class UpdateTourShiftKindsRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Tour's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('tour')->group]);
    }

    /**
     * The id list is present and each entry is a shift kind of the Tour's Group.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_kinds' => ['present', 'array'],
            'shift_kinds.*' => [
                'integer',
                'distinct',
                Rule::exists('shift_kinds', 'id')->where('group_id', $this->route('tour')->group_id),
            ],
        ];
    }
}
