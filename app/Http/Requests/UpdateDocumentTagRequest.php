<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\DocumentTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename a Tag (#717, ADR-0030 §4). Authorized against the DocumentTagPolicy; the capability
 * check here also holds for the super-tier. The new name is unique within the Group.
 */
class UpdateDocumentTagRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var DocumentTag $tag */
        $tag = $this->route('documentTag');

        return $this->libraryAllows($tag->group, 'update', $tag);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DocumentTag $tag */
        $tag = $this->route('documentTag');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('document_tags')->where('group_id', $tag->group_id)->ignore($tag),
            ],
        ];
    }
}
