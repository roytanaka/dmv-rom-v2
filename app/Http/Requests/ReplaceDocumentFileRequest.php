<?php

namespace App\Http\Requests;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Rules\DocumentFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload a new file over a file Document (#713, ADR-0030 §8). The same file rule as an upload.
 * A link Document has no file to replace, so the request is refused for one. Authorized
 * against the DocumentPolicy; the capability check here also holds for the super-tier.
 */
class ReplaceDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document->kind === DocumentKind::File
            && $document->group->has_documents
            && $this->user()->can('update', $document);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [new DocumentFile],
        ];
    }
}
