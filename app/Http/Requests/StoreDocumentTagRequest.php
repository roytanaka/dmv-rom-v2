<?php

namespace App\Http\Requests;

use App\Models\DocumentTag;
use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a Tag in a Group's Document library (#717, ADR-0030 §4). Authorized against the
 * DocumentTagPolicy (Librarian / Chair / super-tier); the capability check here also holds
 * for the super-tier. The name is unique within the Group.
 */
class StoreDocumentTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $group->has_documents
            && $this->user()->can('create', [DocumentTag::class, $group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('document_tags')->where('group_id', $group->id),
            ],
        ];
    }
}
