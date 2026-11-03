<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member admin changes what a Member can do in a language (#918, ADR-0038 §3). The language
 * itself is the row's identity: a wrong one is removed and added again.
 */
class UpdateMemberLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->route('member'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'speaks' => ['required', 'boolean'],
            'reads' => ['required', 'boolean'],
            'writes' => ['required', 'boolean'],
        ];
    }
}
