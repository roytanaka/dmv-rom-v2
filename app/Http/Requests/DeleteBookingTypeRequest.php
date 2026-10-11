<?php

namespace App\Http\Requests;

use App\Models\BookingType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a booking type (#794, ADR-0032 §6) — for one added in error. Authorized through the
 * BookingTypePolicy's `manage` gate. A type any Booking uses is refused with a validation error,
 * even for the super-tier: that is a data-integrity rule, not an authority question. Retire it
 * instead.
 */
class DeleteBookingTypeRequest extends FormRequest
{
    /**
     * Authorize against the BookingTypePolicy on the route-bound type's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [BookingType::class, $this->route('bookingType')->group]);
    }

    /**
     * No body fields accompany a delete.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Refuse while any Booking uses the type.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('bookingType')->bookings()->exists()) {
                $validator->errors()->add('booking_type', trans('group.booking_types.cannot_delete'));
            }
        });
    }
}
