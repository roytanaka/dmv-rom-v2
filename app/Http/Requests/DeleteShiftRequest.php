<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Deleting a Shift (#356, PRD #352, ADR-0021 §2) — cancelling it. Authorization lives
 * here, delegating to the ShiftPolicy (a schedule admin of the owning Group), and (once
 * Sign-ups exist, #357) only at zero Sign-ups: cancelling is never silent. There are no
 * body fields to validate.
 */
class DeleteShiftRequest extends FormRequest
{
    /**
     * Authorize against the ShiftPolicy: the actor must be able to delete the
     * route-bound Shift.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('shift'));
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
     * Refuse a Booking's Shift (#795, ADR-0032 §1): its times and docents needed are the
     * Booking's, changed through the Booking. A validation rule, not the policy, so it binds
     * super-tier too, while the schedule-admin gate still opens the Shift's seats.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('shift')->isBooking()) {
                $validator->errors()->add('shift', trans('group.bookings.booking_shift_locked'));
            }
        });
    }
}
