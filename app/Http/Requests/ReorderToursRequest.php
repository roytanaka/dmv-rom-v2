<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reordering a Group's Tours (#788) — the id list in display order. Authorized through the
 * TourPolicy's `manage` gate; each id must name a Tour of this Group.
 */
class ReorderToursRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('group')]);
    }

    /**
     * The id list is present and each entry is a Tour of this Group.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['present', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('tours', 'id')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
