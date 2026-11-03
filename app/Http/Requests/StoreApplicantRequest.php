<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesApplication;
use App\Models\Applicant;
use App\Models\Member;
use App\Support\Applicants\ApplicantOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A holder member enters a paper or phone application (#928, ADR-0038 §8): the application's
 * fields plus the mentor, any member of the holder. An email a Member already has is refused
 * under `duplicate_email` until the office confirms it with `override_duplicate_email`, so a
 * returning Donor or resigned Member is noticed before a second record starts.
 */
class StoreApplicantRequest extends FormRequest
{
    use ValidatesApplication;

    public function authorize(): bool
    {
        return $this->user()->can('create', Applicant::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLanguages();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->applicationRules(),
            'mentor_id' => ['nullable', 'integer', Rule::in(ApplicantOptions::mentorIds())],
            'override_duplicate_email' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->boolean('override_duplicate_email')) {
                    return;
                }

                if (Member::where('email', $this->input('email'))->exists()) {
                    $validator->errors()->add('duplicate_email', __('applicants.duplicate_email'));
                }
            },
        ];
    }
}
