<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChoosesTour;
use App\Policies\SignUpPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing the Tour on a Sign-up (#791, ADR-0033 §6). Authorization delegates to
 * {@see SignUpPolicy::changeTour}: the seat-holder until the Shift starts, or a schedule admin
 * any time. The field rules follow the actor: a schedule admin may set any of the kind's active
 * Tours or leave it blank, as when placing; anyone else is held to the self sign-up check, a
 * Tour they may give, required.
 */
class UpdateSignUpTourRequest extends FormRequest
{
    use ChoosesTour;

    public function authorize(): bool
    {
        return $this->user()->can('changeTour', $this->route('signUp'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $signUp = $this->route('signUp');
        $shift = $signUp->shift;

        // The schedule-admin gate on the Shift (ShiftPolicy::update) picks the officer rules.
        return $this->user()->can('update', $shift)
            ? $this->officerTourRules($shift)
            : $this->tourRules($shift, $this->user());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tourMessages();
    }
}
