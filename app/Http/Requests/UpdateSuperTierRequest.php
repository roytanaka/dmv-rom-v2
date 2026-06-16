<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Grant or revoke super-tier (#153, ADR-0017 §1). Authorization lives here, not in
 * the controller: only a super-tier actor may flip the org-wide grant, decided by
 * the `manage-super-tier` gate (which the Gate::before super-tier short-circuit is
 * the sole way to pass). The field itself is intentionally not mass-assignable —
 * the controller sets it directly off this validated boolean.
 */
class UpdateSuperTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-super-tier') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'super_tier' => ['required', 'boolean'],
        ];
    }
}
