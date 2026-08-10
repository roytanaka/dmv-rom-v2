<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dropping a Shift (#357, PRD #352, ADR-0021 §Sign-up) — cancelling a Sign-up. Authorization
 * lives here, delegating to the SignUpPolicy: a Member may drop the seat they hold, and
 * **cancel has no deadline** (the policy carries no temporal guard — legacy's `+2 days` was
 * commented out with "allow cancel ANY TIME"). There are no body fields to validate.
 */
class DeleteSignUpRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must be able to drop the route-bound
     * Sign-up (the Member who holds it).
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('signUp'));
    }

    /**
     * No body fields accompany a drop.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
