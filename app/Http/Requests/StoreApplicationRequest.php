<?php

namespace App\Http\Requests;

use App\Http\Controllers\ApplicationController;
use App\Http\Requests\Concerns\ValidatesApplication;
use App\Models\MemberLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * A visitor's application from the public form (#930, ADR-0038 §8, §14.6). Only a visitor who
 * entered the Applicant code in this session may send it, and only while the code is still
 * published and unexpired. The fields are the application's minus what only the office keeps
 * (business phone, country, lifetime membership); the languages are English, French, ASL, LSQ and
 * one other.
 */
class StoreApplicationRequest extends FormRequest
{
    use ValidatesApplication;

    /** The application fields the public form does not take. */
    private const OFFICE_ONLY = ['business_phone', 'address_country', 'membership_lifetime'];

    public function authorize(): bool
    {
        return ApplicationController::codeAccepted($this);
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
        return Arr::except($this->applicationRules(), self::OFFICE_ONLY);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $others = collect($this->input('languages', []))
                    ->filter(fn ($row) => is_array($row) && ! in_array($row['language_code'] ?? null, MemberLanguage::KNOWN_CODES, true));

                if ($others->count() > 1) {
                    $validator->errors()->add('languages', __('apply.one_other_language'));
                }
            },
        ];
    }
}
