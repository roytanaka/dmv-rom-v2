<?php

namespace App\Http\Requests;

use App\Models\Applicant;
use App\Policies\ApplicantPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete an Applicant (#929, ADR-0038 §8), through `ApplicantPolicy::delete`: whoever may edit it,
 * and only while no Member is linked. The confirmation is the record's dialog; nothing is posted.
 */
class DeleteApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The link check is repeated outside the Gate: the super-tier's Gate::before would pass
        // `delete` for an accepted Applicant.
        return $this->user()->can('delete', $this->applicant())
            && ApplicantPolicy::deletable($this->applicant());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    private function applicant(): Applicant
    {
        return $this->route('applicant');
    }
}
