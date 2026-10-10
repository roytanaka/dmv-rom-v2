<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a Tour (#788) — for one added in error. Authorized through the TourPolicy's `manage`
 * gate. A Tour a qualification or Sign-up points at is refused with a validation error, even for
 * the super-tier: that is a data-integrity rule, not an authority question. Retire it instead.
 */
class DeleteTourRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Tour's Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('tour')->group]);
    }

    /**
     * No body fields accompany a delete.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Refuse while anything points at the Tour.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tour = $this->route('tour');

            if ($tour->qualifications()->exists() || $tour->signUps()->exists() || $tour->bookings()->exists()) {
                $validator->errors()->add('tour', trans('group.tours.cannot_delete'));
            }
        });
    }
}
