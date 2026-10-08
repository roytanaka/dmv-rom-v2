<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use App\Models\Group;
use App\Rules\UniqueCategoryName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a Document category to the library root's list (`folder_id` null) or to one Folder's
 * (#724, ADR-0030 §4). Names are unique within that list. Authorized through the
 * DocumentPolicy's `manage`; the capability check here also holds for the super-tier.
 */
class StoreDocumentCategoryRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        $group = $this->group();

        return $this->libraryAllows($group, 'manage', [Document::class, $group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->group();
        $folderId = $this->filled('folder_id') ? $this->integer('folder_id') : null;

        return [
            'name' => ['required', 'string', 'max:255', new UniqueCategoryName($group->id, $folderId)],
            'folder_id' => ['nullable', 'integer', $this->folderOfGroup($group->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => trans('document_categories.error.name_required'),
            'folder_id.exists' => trans('document_folders.error.not_found'),
        ];
    }

    private function group(): Group
    {
        /** @var Group */
        return $this->route('group');
    }
}
