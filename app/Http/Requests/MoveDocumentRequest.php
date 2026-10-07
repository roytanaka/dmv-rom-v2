<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Move a Document to another Folder of its Group, or to the library root (`folder_id` null)
 * (#714, ADR-0030 §3). Authorized through the DocumentPolicy's `move` ability.
 */
class MoveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->document();

        return $document->group->has_documents
            && $this->user()->can('move', $document);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['present', 'nullable', 'integer', Rule::exists('document_folders', 'id')->where('group_id', $this->document()->group_id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folder_id.exists' => trans('document_folders.error.not_found'),
        ];
    }

    private function document(): Document
    {
        /** @var Document */
        return $this->route('document');
    }
}
