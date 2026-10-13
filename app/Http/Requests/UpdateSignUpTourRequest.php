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
        $shift = $this->route('signUp')->shift;

        return $this->byOfficer()
            ? $this->officerTourRules($shift)
            : $this->tourRules($shift, $this->user());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->byOfficer() ? $this->officerTourMessages() : $this->tourMessages();
    }

    /**
     * Whether the actor writes as a schedule admin (ShiftPolicy::update on the Shift), which picks
     * the officer rules and messages (#791, #806).
     */
    private function byOfficer(): bool
    {
        return $this->user()->can('update', $this->route('signUp')->shift);
    }
}
