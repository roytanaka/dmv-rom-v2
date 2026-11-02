<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting an Invitation (ADR-0034 §6): its Host or the super-tier, through the
 * InvitationPolicy. There is no body to validate.
 */
class DeleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('invitation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
