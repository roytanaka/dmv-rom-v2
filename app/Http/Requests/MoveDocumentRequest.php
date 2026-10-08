<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Move a Document to another Folder of its Group, or to the library root (`folder_id` null)
 * (#714, ADR-0030 §3). The move clears its Document category, or sets one of the
 * destination's (#728, §4). Authorized through the DocumentPolicy's `move` ability.
 */
class MoveDocumentRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        $document = $this->document();

        return $this->libraryAllows($document->group, 'move', $document);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $groupId = $this->document()->group_id;

        return [
            'folder_id' => ['present', 'nullable', 'integer', $this->folderOfGroup($groupId)],
            // Its section in the destination (#728, ADR-0030 §4): one of the new Folder's (or the
            // root's) Document categories. Absent or null, the move files it under Other.
            'category_id' => ['nullable', 'integer', $this->categoryOf($groupId, $this->nullableId('folder_id'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folder_id.exists' => trans('document_folders.error.not_found'),
            ...$this->categoryMessages(),
        ];
    }

    private function document(): Document
    {
        /** @var Document */
        return $this->route('document');
    }
}
