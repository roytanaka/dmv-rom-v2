<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete a Document (#713, ADR-0030 §8). Authorized against the DocumentPolicy; the
 * capability check here also holds for the super-tier. No input to validate.
 */
class DeleteDocumentRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $this->libraryAllows($document->group, 'delete', $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
