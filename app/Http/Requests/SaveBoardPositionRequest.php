<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member admin adds or changes a board position on the Member record (#918, ADR-0038 §3).
 */
class SaveBoardPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->route('member'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'string', 'max:255'],
            'years' => ['nullable', 'string', 'max:255'],
        ];
    }
}
