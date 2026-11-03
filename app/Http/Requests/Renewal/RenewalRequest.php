<?php

namespace App\Http\Requests\Renewal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A request on the signed-in Member's own Renewal (#939, ADR-0038 §9). Every step's GET and POST
 * resolves it, so a Member who need not renew is refused at each step. The routes take no Member:
 * every step acts on `$request->user()`, so a Member cannot open another's Renewal. Eligibility is
 * read directly, not through a Gate, so the super-tier's Gate::before grants nothing here.
 * Each step's POST extends this with its own rules.
 */
class RenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->renewalEligibility()->eligible();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
