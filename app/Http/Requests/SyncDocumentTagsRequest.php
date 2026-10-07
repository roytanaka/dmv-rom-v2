<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\DocumentTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set which Tags a Document carries (#717, ADR-0030 §4). `tags` is the complete intended set
 * of Tag ids, each one of the Document's own Group. Authorized against the
 * DocumentTagPolicy's `assign`; the capability check here also holds for the super-tier.
 */
class SyncDocumentTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document->group->has_documents
            && $this->user()->can('assign', [DocumentTag::class, $document]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');

        return [
            'tags' => ['present', 'array'],
            'tags.*' => [
                'integer', 'distinct',
                Rule::exists('document_tags', 'id')->where('group_id', $document->group_id),
            ],
        ];
    }
}
