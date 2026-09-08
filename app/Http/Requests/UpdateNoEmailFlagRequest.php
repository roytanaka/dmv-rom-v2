<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set or clear the no-email flag (#483, ADR-0024 §9). Authorization lives here, not
 * in the controller: it is a member-administration action, so the `administer-members`
 * gate decides — held via the Records stewardship (ADR-0011), with super-tier passing
 * through the Gate::before short-circuit. A Chair or the Member themself is denied.
 */
class UpdateNoEmailFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer-members') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'no_email' => ['required', 'boolean'],
        ];
    }
}
