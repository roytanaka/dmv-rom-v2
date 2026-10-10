<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entering a Group's exhibition revenue for a month (#800, ADR-0032 §12). Authorized through the
 * BookingPolicy's `enterExhibitionRevenue` gate (the Statistician or Chair, plus the super-tier).
 * Null clears the month; 0 is a valid figure.
 */
class UpdateExhibitionRevenueRequest extends FormRequest
{
    /**
     * Authorize against the BookingPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('enterExhibitionRevenue', [Booking::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year_month' => ['required', 'string', 'regex:/^\d{4}(0[1-9]|1[0-2])$/'],
            'amount' => ['present', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }
}
