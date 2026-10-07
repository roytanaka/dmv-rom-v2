<?php

namespace App\Http\Requests;

use App\Models\DocumentFolder;
use App\Rules\UniqueFolderName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Move a Folder, with everything in it, to another parent in the same Group or to the top
 * level (`parent_id` null) (#714, ADR-0030 §3). Refused when the target is the Folder itself
 * or one of its descendants, when the moved subtree would end up past
 * {@see DocumentFolder::MAX_DEPTH}, or when the target already has a Folder of that name.
 */
class MoveDocumentFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->folder();

        return $folder->group->has_documents
            && $this->user()->can('update', $folder);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['present', 'nullable', 'integer', Rule::exists('document_folders', 'id')->where('group_id', $this->folder()->group_id)],
        ];
    }

    /**
     * Cycle, depth and sibling-name checks, once the target is known to be one of the Group's
     * Folders (or the top level).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('parent_id')) {
                    return;
                }

                $folder = $this->folder();
                $target = $this->filled('parent_id')
                    ? DocumentFolder::query()->findOrFail($this->integer('parent_id'))
                    : null;

                if ($target !== null && ($target->is($folder) || in_array($target->id, $folder->descendantIds(), true))) {
                    $validator->errors()->add('parent_id', trans('document_folders.error.into_itself'));

                    return;
                }

                if (($target?->depth() ?? 0) + $folder->subtreeHeight() > DocumentFolder::MAX_DEPTH) {
                    $validator->errors()->add('parent_id', trans('document_folders.error.too_deep', ['max' => DocumentFolder::MAX_DEPTH]));

                    return;
                }

                (new UniqueFolderName($folder->group_id, $target?->id, $folder->id))
                    ->validate('parent_id', $folder->name, fn (string $message) => $validator->errors()->add('parent_id', $message));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => trans('document_folders.error.not_found'),
        ];
    }

    private function folder(): DocumentFolder
    {
        /** @var DocumentFolder */
        return $this->route('folder');
    }
}
