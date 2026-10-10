<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming, retiring, restoring a Tour, or setting its open-to-all flag (#788, ADR-0033 §1,
 * §6). Authorized through the TourPolicy's `manage` gate on the Tour's own Group, so an officer
 * of another Group is refused. Every field is `sometimes`, so a partial payload is valid.
 * Retiring is always allowed, whatever points at the Tour.
 */
class UpdateTourRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Tour's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('tour')->group]);
    }

    /**
     * The name stays unique among the Group's other Tours.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tour = $this->route('tour');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tours', 'name')
                    ->where('group_id', $tour->group_id)
                    ->ignore($tour->getKey()),
            ],
            'active' => ['sometimes', 'boolean'],
            'open_to_all' => ['sometimes', 'boolean'],
        ];
    }
}
