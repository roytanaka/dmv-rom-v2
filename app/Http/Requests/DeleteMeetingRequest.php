<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a meeting (#193, PRD #186). Authorization lives here, delegating to the
 * MeetingPolicy (an officer of the owning Group, or the super-tier); there are no
 * fields to validate. Deleting a meeting cascades to its links at the database.
 */
class DeleteMeetingRequest extends FormRequest
{
    /**
     * Authorize against the MeetingPolicy: the actor must be able to delete the
     * route-bound meeting.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('meeting'));
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
