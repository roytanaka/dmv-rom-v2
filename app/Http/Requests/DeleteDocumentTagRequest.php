<?php

namespace App\Http\Requests;

use App\Models\DocumentTag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete a Tag (#717, ADR-0030 §4). Authorized against the DocumentTagPolicy; the capability
 * check here also holds for the super-tier. The pivot rows cascade, so the Tag leaves every
 * Document that carried it.
 */
class DeleteDocumentTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DocumentTag $tag */
        $tag = $this->route('documentTag');

        return $tag->group->has_documents
            && $this->user()->can('delete', $tag);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
