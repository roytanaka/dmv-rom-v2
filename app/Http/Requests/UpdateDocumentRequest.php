<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a Document's title and description (#713, ADR-0030 §8). Both are single-column
 * content kept as written (ADR-0004); a blank title clears it, so the row shows the filename.
 * Authorized against the DocumentPolicy; the capability check here also holds for the
 * super-tier.
 */
class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document->group->has_documents
            && $this->user()->can('update', $document);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
