<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a Booking (#796, ADR-0032 §1, §3) with its Shift and Sign-ups. Authorized through the
 * BookingPolicy's `delete` gate: a Booker, the Chair, the Statistician or super-tier. Unlike
 * deleting a Shift, seated Sign-ups do not block it: the client cancelled, and the form names the
 * Members it removes before confirming. There are no body fields to validate.
 */
class DeleteBookingRequest extends FormRequest
{
    /**
     * Authorize against the BookingPolicy on the route-bound Booking.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('booking'));
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
}
