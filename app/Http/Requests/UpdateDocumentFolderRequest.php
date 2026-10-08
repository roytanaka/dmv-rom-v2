<?php

namespace App\Http\Requests;

use App\Enums\DocumentVisibility;
use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\DocumentFolder;
use App\Rules\UniqueFolderName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename a Folder (#714, ADR-0030 §3), and set a top-level Folder's visibility (#715, §5). The
 * name stays unique among its siblings. Moving is its own request
 * ({@see MoveDocumentFolderRequest}).
 */
class UpdateDocumentFolderRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        $folder = $this->folder();

        return $this->libraryAllows($folder->group, 'update', $folder);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $folder = $this->folder();

        return [
            'name' => ['required', 'string', 'max:255', new UniqueFolderName($folder->group_id, $folder->parent_id, $folder->id)],
            // A top-level Folder's setting (#715, ADR-0030 §5); left out, it stays as it is.
            'visibility' => [Rule::prohibitedIf($folder->parent_id !== null), 'sometimes', Rule::enum(DocumentVisibility::class)],
            // Its section in the parent Folder (#724, ADR-0030 §4): one of the parent's (or the
            // root's) Document categories; null files it under Other. Left out, it stays.
            'category_id' => ['sometimes', 'nullable', 'integer', $this->categoryOf($folder->group_id, $folder->parent_id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => trans('document_folders.error.name_required'),
            'visibility.prohibited' => trans('document_folders.error.visibility_top_level'),
            'visibility.enum' => trans('document_folders.error.visibility_invalid'),
            ...$this->categoryMessages(),
        ];
    }

    private function folder(): DocumentFolder
    {
        /** @var DocumentFolder */
        return $this->route('folder');
    }
}
