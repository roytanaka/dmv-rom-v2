<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a link Document's title and web address (#716, ADR-0030 §7). Authorized like an
 * upload to the Document's Group (Librarian / Chair / super-tier), only while the documents
 * capability is on. The controller 404s a file Document.
 */
class UpdateLinkDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document->group->has_documents
            && $this->user()->can('create', [Document::class, $document->group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreLinkDocumentRequest::linkRules();
    }
}
