<?php

namespace App\Http\Requests\Settings;

use App\Models\Member;
use App\Rules\ProfilePhoto;
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Member::class)->ignore($this->user()->id),
            ],
            // Contact record (#232) — all optional. `phone` is the primary number;
            // the two secondary numbers and the structured home address are edited
            // from the same form. Identity (name, email) stays required above.
            'phone' => ['nullable', 'string', 'max:255'],
            'alternate_phone' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:255'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'address_province' => ['nullable', 'string', 'max:255'],
            // Canadian postal code (A1A 1A1), optional space — the one contact field
            // with a format, so a typo surfaces as validation feedback.
            'address_postal_code' => ['nullable', 'string', 'regex:/^[A-Za-z]\d[A-Za-z][ ]?\d[A-Za-z]\d$/'],
            'address_country' => ['nullable', 'string', 'max:255'],
            // Optional profile photo (#233). Format/size are gated by ProfilePhoto so
            // HEIC, unsupported types, and oversized files fail with actionable, tier-
            // specific messages rather than a generic file error. Absent field = no
            // change; the controller only touches photo_path when a file is present.
            'photo' => ['nullable', 'file', new ProfilePhoto],
            // PRD #228: re-authenticate on email change so a stray session can't hijack
            // the account. Rule::when so current_password is validated ONLY on an email
            // change — the form always sends the field (empty), and ConvertEmptyStrings-
            // ToNull turns '' into null, which the bare `current_password` rule would
            // otherwise treat as present-and-wrong and reject on every save.
            'current_password' => Rule::when(
                $this->emailIsChanging(),
                ['required', 'current_password'],
            ),
        ];
    }

    protected function emailIsChanging(): bool
    {
        return $this->input('email') !== $this->user()->email;
    }
}
