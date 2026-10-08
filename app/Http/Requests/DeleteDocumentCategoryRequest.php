<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete a Document category (#724, ADR-0030 §4). Its items move to Other. Authorized through
 * the DocumentPolicy's `manage`; no input to validate.
 */
class DeleteDocumentCategoryRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var DocumentCategory $category */
        $category = $this->route('category');

        return $this->libraryAllows($category->group, 'manage', [Document::class, $category->group]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
