<?php

namespace App\Http\Requests;

use App\Enums\ApplicantStatus;
use App\Enums\DepartureReason;
use App\Http\Requests\Concerns\ValidatesApplication;
use App\Models\Applicant;
use App\Support\Applicants\ApplicantOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A save of the Applicant record (#928, ADR-0038 §8): every field, the intake selects and the
 * languages. The status date is never sent: it moves with the status. `notes` is absent because
 * the log only grows, through `new_note`. A mentor or placement no longer on offer stays valid for
 * the Applicant that already has it.
 */
class UpdateApplicantRequest extends FormRequest
{
    use ValidatesApplication;

    private const TEXT = ['nullable', 'string', 'max:65535'];

    private function target(): Applicant
    {
        return $this->route('applicant');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
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
        $applicant = $this->target();

        return [
            ...$this->applicationRules(),
            'status' => ['required', Rule::enum(ApplicantStatus::class)],
            'departure_reason' => [
                'nullable',
                'required_if:status,'.ApplicantStatus::Incomplete->value,
                Rule::enum(DepartureReason::class),
            ],
            'placement_group_id' => ['nullable', 'integer', Rule::in([...ApplicantOptions::placementIds(), $applicant->placement_group_id])],
            'mentor_id' => ['nullable', 'integer', Rule::in([...ApplicantOptions::mentorIds(), $applicant->mentor_id])],
            'applied_on' => ['required', 'date'],
            'entry_year' => ['nullable', 'integer', 'between:1900,2100'],
            'volunteer_experience' => self::TEXT,
            'past_experience' => self::TEXT,
            'education' => self::TEXT,
            'new_note' => self::TEXT,
        ];
    }
}
