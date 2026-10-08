<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Rules\UniqueCategoryName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rename a Document category (#724, ADR-0030 §4). The name stays unique within its owning
 * Folder's list. Authorized through the DocumentPolicy's `manage`.
 */
class UpdateDocumentCategoryRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        $category = $this->category();

        return $this->libraryAllows($category->group, 'manage', [Document::class, $category->group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->category();

        return [
            'name' => ['required', 'string', 'max:255', new UniqueCategoryName($category->group_id, $category->folder_id, $category->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => trans('document_categories.error.name_required')];
    }

    private function category(): DocumentCategory
    {
        /** @var DocumentCategory */
        return $this->route('category');
    }
}
