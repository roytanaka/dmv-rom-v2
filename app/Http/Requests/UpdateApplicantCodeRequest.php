<?php

namespace App\Http\Requests;

use App\Models\Applicant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Applicant Chair sets the Applicant code (#930, ADR-0038 §8): the code, whether the sign-in
 * page offers it, and the last day it is good for. Publishing needs a code and an expiry.
 */
class UpdateApplicantCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setCode', Applicant::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'required_if_accepted:published', 'string', 'max:64'],
            'published' => ['boolean'],
            'expires_on' => ['nullable', 'required_if_accepted:published', 'date'],
        ];
    }
}
