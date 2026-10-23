<?php

namespace App\Http\Requests;

use App\Models\BookingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming, re-rating, retiring or restoring a booking type (#794, ADR-0032 §6). Authorized
 * through the BookingTypePolicy's `manage` gate on the type's own Group, so a Booker of another
 * Group is refused. Every field is `sometimes`, so a partial payload is valid. Retiring is always
 * allowed, whatever Bookings use the type.
 */
class UpdateBookingTypeRequest extends FormRequest
{
    /**
     * Authorize against the BookingTypePolicy on the route-bound type's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [BookingType::class, $this->route('bookingType')->group]);
    }

    /**
     * The name stays unique among the Group's other types.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('bookingType');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('booking_types', 'name')
                    ->where('group_id', $type->group_id)
                    ->ignore($type->getKey()),
            ],
            'rate_per_visitor' => ['sometimes', 'required', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'rate_per_docent_hour' => ['sometimes', 'required', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
