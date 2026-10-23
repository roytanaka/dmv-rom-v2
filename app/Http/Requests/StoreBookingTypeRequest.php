<?php

namespace App\Http\Requests;

use App\Models\BookingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a booking type to a Group (#794, ADR-0032 §6) — name and both rates. Authorized through
 * the BookingTypePolicy's `manage` gate on the route-bound Group (bookings on, actor a Booker or
 * Chair). The name is content, never translated (ADR-0004), and unique within the Group.
 */
class StoreBookingTypeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('booking_types', 'name')->where('group_id', $this->route('group')->getKey()),
            ],
            'rate_per_visitor' => ['required', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'rate_per_docent_hour' => ['required', 'decimal:0,2', 'min:0', 'max:999999.99'],
        ];
    }
}
