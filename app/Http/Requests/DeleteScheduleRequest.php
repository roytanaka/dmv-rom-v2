<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Deleting a Schedule (#354, PRD #352, ADR-0021 §1). Authorization lives here,
 * delegating to the SchedulePolicy — a Scheduler / Chair of the owning Group (plus the
 * super-tier), and (once Sign-ups exist, #357) only at zero Sign-ups: a Schedule with
 * history is permanent. There are no body fields to validate.
 */
class DeleteScheduleRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy: the actor must be able to delete the
     * route-bound Schedule.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('schedule'));
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
     * Refuse a group-tour Schedule that still holds Bookings (#796, ADR-0032 §4): deleting it
     * would cascade to them. A validation rule, not the policy, so it binds super-tier too. Once
     * its Bookings are moved or deleted, the ordinary rules above apply.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('schedule')->holdsBookings()) {
                $validator->errors()->add('schedule', trans('group.bookings.group_tour_schedule_holds_bookings'));
            }
        });
    }
}
