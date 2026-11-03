<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member admin emails a Saved query's results to chosen Members (#924, ADR-0038 §7), with an
 * optional note.
 */
class EmailSavedQueryResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('run', $this->route('savedQuery'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
