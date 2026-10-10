<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Changing a Booking (#796, ADR-0032 §1, §3, §4) — any field, the date, times and docents needed
 * included. Authorized through the BookingPolicy's `update` gate: a Booker, the Chair, the
 * Statistician or super-tier.
 *
 * The same form as adding ({@see StoreBookingRequest}), with two differences. The Booking's own
 * Tour and booking type stay valid once retired, so an old Booking stays editable, but it can never
 * move onto a different retired one. And the docents needed may not drop below the Shift's current
 * Sign-ups: the Booker removes people first, visibly. The Earned correction (#797) is never part of
 * this form.
 */
class UpdateBookingRequest extends StoreBookingRequest
{
    /**
     * Authorize against the BookingPolicy on the route-bound Booking.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->booking());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $booking = $this->booking();

        return [
            ...self::staffingRules(),
            ...self::clientRules($booking->group_id),
            'tour_id' => [
                'required',
                'integer',
                Rule::exists('tours', 'id')
                    ->where('group_id', $booking->group_id)
                    ->where(fn ($query) => $query->where('active', true)->orWhere('id', $booking->tour_id)),
            ],
            'booking_type_id' => [
                'required',
                'integer',
                Rule::exists('booking_types', 'id')
                    ->where('group_id', $booking->group_id)
                    ->where(fn ($query) => $query->where('active', true)->orWhere('id', $booking->booking_type_id)),
            ],
        ];
    }

    /**
     * Refuse docents needed below the Shift's current Sign-up count (the same rule as a Shift's
     * capacity, #357).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $docents = $this->input('docents_needed');

            if (is_numeric($docents) && (int) $docents < $this->booking()->shift->signUps()->count()) {
                $validator->errors()->add('docents_needed', trans('group.scheduling_panel.capacity_below_signups'));
            }
        });
    }

    /**
     * The route-bound Booking.
     */
    public function booking(): Booking
    {
        return $this->route('booking');
    }
}
