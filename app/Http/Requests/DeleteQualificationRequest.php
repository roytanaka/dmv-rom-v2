<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Removing a qualification (#789, ADR-0033 §4) — the Member no longer gives the Tour. Authorized
 * through the TourPolicy's `manage` gate on the qualification's Tour's Group.
 */
class DeleteQualificationRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the qualification's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('qualification')->tour->group]);
    }

    /**
     * No input.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
