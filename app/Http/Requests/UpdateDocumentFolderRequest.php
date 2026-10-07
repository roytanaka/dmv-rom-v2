<?php

namespace App\Http\Requests;

use App\Models\DocumentFolder;
use App\Rules\UniqueFolderName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rename a Folder (#714, ADR-0030 §3). The name stays unique among its siblings. Moving is
 * its own request ({@see MoveDocumentFolderRequest}).
 */
class UpdateDocumentFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->folder();

        return $folder->group->has_documents
            && $this->user()->can('update', $folder);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $folder = $this->folder();

        return [
            'name' => ['required', 'string', 'max:255', new UniqueFolderName($folder->group_id, $folder->parent_id, $folder->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => trans('document_folders.error.name_required'),
        ];
    }

    private function folder(): DocumentFolder
    {
        /** @var DocumentFolder */
        return $this->route('folder');
    }
}
