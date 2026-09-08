<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
