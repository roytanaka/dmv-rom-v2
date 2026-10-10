<?php

namespace App\Http\Requests;

use App\Models\SignUp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Handing a seat on a Booking to a substitute (#798, ADR-0032 §8). The seat-holder names the
 * Member who takes it; the seat moves in one write. `authorize()` asks SignUpPolicy::substitute
 * (the holder, on a Booking's Shift, before it starts). The named Member must be one of
 * {@see SignUp::eligibleSubstitutes()}: in the Group, not already on the Shift, and passing the
 * self sign-up test.
 */
class SubstituteSignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('substitute', $this->route('signUp'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer'],
        ];
    }

    /**
     * The named Member must be able to take the seat themselves.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $eligible = $this->route('signUp')->eligibleSubstitutes()->contains('id', $this->integer('member_id'));

            if (! $eligible) {
                $validator->errors()->add('member_id', trans('group.scheduling_panel.substitute.not_eligible'));
            }
        });
    }
}
