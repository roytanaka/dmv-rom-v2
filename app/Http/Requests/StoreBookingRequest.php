<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Adding a Booking to a Group (#795, ADR-0032 §1, §3, §10). Authorized through the BookingPolicy's
 * `create` gate on the route-bound Group (bookings and scheduling on, actor a Booker or Chair).
 *
 * The form is {@see DescribesBooking}: the Tour and booking type must be the Group's own and
 * active, since a retired one is no longer offered. The Group must have a group-tour shift kind
 * set (Group Settings) for the Shift to take.
 */
class StoreBookingRequest extends FormRequest
{
    use DescribesBooking;

    /**
     * Authorize against the BookingPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Booking::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->bookingRules($this->route('group')->getKey());
    }

    /**
     * Refuse while the Group has no group-tour shift kind to give the Shift.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('group')->group_tour_shift_kind_id === null) {
                $validator->errors()->add('date', trans('group.bookings.no_shift_kind'));
            }
        });
    }
}
