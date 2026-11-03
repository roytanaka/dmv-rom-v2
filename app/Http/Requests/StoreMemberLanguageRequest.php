<?php

namespace App\Http\Requests;

use App\Models\MemberLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member admin adds a language to the Member record (#918, ADR-0038 §3). A known language typed
 * as an "other" one folds into its code, so it cannot dodge the one-row rule or the speak-only rule.
 */
class StoreMemberLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->route('member'));
    }

    protected function prepareForValidation(): void
    {
        $code = mb_strtolower((string) $this->input('language_code'));

        if (in_array($code, MemberLanguage::KNOWN_CODES, true)) {
            $this->merge(['language_code' => $code]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique(MemberLanguage::class)->where('member_id', $this->route('member')->id),
            ],
            'speaks' => ['nullable', 'boolean'],
            'reads' => ['nullable', 'boolean'],
            'writes' => ['nullable', 'boolean'],
        ];
    }
}
