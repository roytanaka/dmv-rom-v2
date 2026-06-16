<?php

namespace App\Http\Requests\Settings;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Authorize the self-service edit against the MemberPolicy (ADR-0017): the
     * actor is editing their own record, so this resolves to the self branch. The
     * check is explicit rather than a blind `return true` — the policy is the one
     * place that decides who may edit a member.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
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
                Rule::unique(Member::class)->ignore($this->user()->id),
            ],
        ];
    }
}
