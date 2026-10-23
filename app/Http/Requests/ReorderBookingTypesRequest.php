<?php

namespace App\Http\Requests;

use App\Models\BookingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reordering a Group's booking types (#794) — the id list in display order. Authorized through
 * the BookingTypePolicy's `manage` gate; each id must name a type of this Group.
 */
class ReorderBookingTypesRequest extends FormRequest
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
            'ids' => ['present', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('booking_types', 'id')->where('group_id', $this->route('group')->getKey()),
            ],
        ];
    }
}
