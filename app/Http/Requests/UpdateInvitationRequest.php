<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing an Invitation (ADR-0034). Authorized against the InvitationPolicy: its Host
 * or the super-tier. An Option id must belong to this Invitation, so an edit cannot
 * reach into another Invitation's Options.
 */
class UpdateInvitationRequest extends FormRequest
{
    use ValidatesInvitation;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invitation'));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->invitationRules(),
            'options.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('options', 'id')->where('invitation_id', $this->route('invitation')->id),
            ],
        ];
    }
}
