<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesInvitation;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating an Invitation (ADR-0034). Authorized against the InvitationPolicy: a
 * Member whose Category gives Full access. The controller passes `validated()`,
 * never `all()`.
 */
class StoreInvitationRequest extends FormRequest
{
    use ValidatesInvitation;

    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return $this->invitationRules();
    }
}
