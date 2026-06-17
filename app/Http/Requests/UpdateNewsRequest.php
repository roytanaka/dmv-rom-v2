<?php

namespace App\Http\Requests;

use App\Models\News;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing a news item (#155, ADR-0017 §4–5). `authorize()` delegates to the
 * NewsPolicy — only a news-editor of the posting Group may edit — and `rules()`
 * whitelists the content fields. The posting Group is fixed at creation and is
 * deliberately not editable here, so attribution can never be reassigned.
 */
class UpdateNewsRequest extends FormRequest
{
    private function target(): News
    {
        return $this->route('news');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
