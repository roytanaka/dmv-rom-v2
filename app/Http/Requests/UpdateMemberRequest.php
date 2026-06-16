<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a member record (ADR-0017 §4, §7). The mutation is authorized
 * structurally here — `authorize()` does the real policy check and never returns
 * `true` blindly — and `rules()` is a whitelist that omits every authority field
 * (category, super-tier, roles), so no client-asserted authority can ride in on
 * the request. The controller passes `validated()`, never `all()`.
 */
class UpdateMemberRequest extends FormRequest
{
    /**
     * The member being edited, resolved from the route binding.
     */
    private function target(): Member
    {
        return $this->route('member');
    }

    /**
     * Authorize the mutation against the MemberPolicy: self by default, Records or
     * super-tier for anyone else.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    /**
     * The whitelist of fields a member edit may set. Authority fields are
     * deliberately absent — they are set only through their own gated actions.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Member::class)->ignore($this->target()->id),
            ],
        ];
    }
}
