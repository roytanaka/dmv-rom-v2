<?php

namespace App\Http\Requests;

use App\Enums\AudienceKey;
use App\Support\Audiences\AudienceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a whole Audience to a guest list (ADR-0034 §3): the Invitation's Host or the
 * super-tier. The browser names the Audience and never posts a list. Whether this actor
 * may pick it is the {@see AudienceResolver}'s to enforce, as on a Broadcast send.
 */
class StoreGuestAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageGuests', $this->route('invitation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::enum(AudienceKey::class)->only([AudienceKey::AllMembers, AudienceKey::WholeGroup])],
            'parameter' => ['nullable', 'string'],
        ];
    }
}
