<?php

namespace App\Http\Requests;

use App\Policies\MemberPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reinstate a Resigned Member (#920, ADR-0038 §4), through `MemberPolicy::reinstate`. There is no
 * body to validate.
 */
class ReinstateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The group check is repeated outside the Gate: the super-tier's Gate::before would pass
        // `reinstate` for a Member who is not Resigned at all.
        return $this->user()->can('reinstate', $this->route('member'))
            && MemberPolicy::reinstatable($this->route('member'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
