<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\WritesDocumentLibrary;
use App\Models\Document;
use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a link Document to a Group's Document library (#716, ADR-0030 §7): a title and an
 * http(s) web address, no stored file. Authorized like an upload (Librarian / Chair /
 * super-tier); the capability check here also holds for the super-tier.
 */
class StoreLinkDocumentRequest extends FormRequest
{
    use WritesDocumentLibrary;

    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $this->libraryAllows($group, 'create', [Document::class, $group]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        return [
            ...self::linkRules(),
            // The Folder the link lands in (#714); absent or null is the library root.
            'folder_id' => ['nullable', 'integer', $this->folderOfGroup($group->id)],
            // Its section in that Folder (#728, ADR-0030 §4): one of the Folder's (or the root's)
            // Document categories; null or absent files it under Other.
            'category_id' => ['nullable', 'integer', $this->categoryOf($group->id, $this->nullableId('folder_id'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->categoryMessages();
    }

    /**
     * The fields of a link Document, shared with {@see UpdateDocumentRequest}. Only http
     * and https pass, so a `javascript:` or `file:` address never reaches the redirect.
     *
     * @return array<string, list<string>>
     */
    public static function linkRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
