<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
