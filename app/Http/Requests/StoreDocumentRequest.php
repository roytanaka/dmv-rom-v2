<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\Group;
use App\Rules\DocumentFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload one file to a Group's Document library (#712, ADR-0030 §12). One request carries one
 * file; the client sends a multi-file pick as one request per file so each shows its own
 * progress. Authorized against the DocumentPolicy (Librarian / Chair / super-tier); the
 * capability check here also holds for the super-tier.
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $group->has_documents
            && $this->user()->can('create', [Document::class, $group]);
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
