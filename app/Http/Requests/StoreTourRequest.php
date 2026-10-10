<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a Tour to a Group (#788, ADR-0033 §1) — name only. Authorized through the TourPolicy's
 * `manage` gate on the route-bound Group (vetting on, actor a Vetting officer or Chair). The
 * name is content, never translated (ADR-0004), and unique within the Group.
 */
class StoreTourRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('group')]);
    }

    /**
     * The name is required and unique among this Group's Tours.
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
                Rule::unique('tours', 'name')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
