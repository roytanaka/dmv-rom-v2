<?php

namespace App\Http\Requests;

use App\Enums\DocumentVisibility;
use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Rules\UniqueFolderName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create a Folder in a Group's Document library (#714, ADR-0030 §3), at the top level
 * (`parent_id` null) or inside another Folder of the same Group. Refused past
 * {@see DocumentFolder::MAX_DEPTH}. A top-level Folder may carry its `visibility` (#715).
 * Authorized through the DocumentFolderPolicy; the capability
 * check here also holds for the super-tier.
 */
class StoreDocumentFolderRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        $group = $this->group();

        return $this->libraryAllows($group, 'create', [DocumentFolder::class, $group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->group();
        $parentId = $this->filled('parent_id') ? $this->integer('parent_id') : null;

        return [
            'name' => ['required', 'string', 'max:255', new UniqueFolderName($group->id, $parentId)],
            'parent_id' => ['nullable', 'integer', $this->folderOfGroup($group->id)],
            // Set on a top-level Folder only (#715, ADR-0030 §5); a subfolder inherits. Left
            // out, a top-level Folder is `group`.
            'visibility' => [Rule::prohibitedIf($parentId !== null), 'nullable', Rule::enum(DocumentVisibility::class)],
        ];
    }

    /**
     * The depth limit, once the parent is known to be one of the Group's Folders.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('parent_id') || ! $this->filled('parent_id')) {
                    return;
                }

                $parent = DocumentFolder::query()->findOrFail($this->integer('parent_id'));

                if ($parent->depth() + 1 > DocumentFolder::MAX_DEPTH) {
                    $validator->errors()->add('parent_id', $this->tooDeepMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => trans('document_folders.error.name_required'),
            'parent_id.exists' => trans('document_folders.error.not_found'),
            'visibility.prohibited' => trans('document_folders.error.visibility_top_level'),
            'visibility.enum' => trans('document_folders.error.visibility_invalid'),
        ];
    }

    private function group(): Group
    {
        /** @var Group */
        return $this->route('group');
    }
}
