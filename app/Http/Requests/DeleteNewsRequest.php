<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a news item (#155, ADR-0017 §4–5). The mutation is authorized
 * structurally here — only a news-editor of the posting Group may delete, via the
 * NewsPolicy. There is no body to validate.
 */
class DeleteNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('news'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
