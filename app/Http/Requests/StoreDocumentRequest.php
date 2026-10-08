<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
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
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $this->libraryAllows($group, 'create', [Document::class, $group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        return [
            'file' => [new DocumentFile],
            // The Folder the upload lands in (#714); absent or null is the library root.
            'folder_id' => ['nullable', 'integer', $this->folderOfGroup($group->id)],
            // Its section in that Folder (#728, ADR-0030 §4): one of the Folder's (or the root's)
            // Document categories; null or absent files it under Other.
            'category_id' => ['nullable', 'integer', $this->categoryOf($group->id, $this->filled('folder_id') ? $this->integer('folder_id') : null)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->categoryMessages();
    }
}
