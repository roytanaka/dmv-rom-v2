<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\DocumentFolder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete a Folder (#714, ADR-0030 §3). Authorized against the DocumentFolderPolicy; the
 * capability check here also holds for the super-tier. No input to validate; emptiness is a
 * separate refusal with its own message, in the controller.
 */
class DeleteDocumentFolderRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var DocumentFolder $folder */
        $folder = $this->route('folder');

        return $this->libraryAllows($folder->group, 'delete', $folder);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
