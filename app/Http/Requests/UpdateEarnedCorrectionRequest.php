<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsMoney;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Setting or clearing the Statistician's correction to a Booking's Earned (#797, ADR-0032 §7).
 * Authorized through the BookingPolicy's `correctEarned` gate (the Statistician or Chair, plus the
 * super-tier). Null clears it and brings the worked-out figure back; 0 is a valid correction.
 */
class UpdateEarnedCorrectionRequest extends FormRequest
{
    use ReadsMoney;

    /**
     * Authorize against the BookingPolicy on the route-bound Booking.
     */
    public function authorize(): bool
    {
        return $this->user()->can('correctEarned', $this->route('booking'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'earned_correction' => ['present', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }
}
