<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing a qualification's Last vet date (#789, ADR-0033 §3) — recording a re-vetting.
 * Authorized through the TourPolicy's `manage` gate on the qualification's Tour's Group, so an
 * officer of another Group is refused.
 */
class UpdateQualificationRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the qualification's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('qualification')->tour->group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'last_vet_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
