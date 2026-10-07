<?php

namespace App\Http\Requests\Concerns;

use App\Models\DocumentFolder;
use App\Models\Group;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The pieces every Document library write shares (#290, ADR-0030): the authorization shape,
 * the "one of this Group's Folders" rule, and the depth-limit message. One home, so the
 * library's Form Requests read the same and nothing drifts.
 */
trait WritesDocumentLibrary
{
    /**
     * The library write check: the Group runs the documents capability, then the policy
     * ability. The capability comes first so it holds for the super-tier too, whom
     * `Gate::before` passes through the policy.
     */
    protected function libraryAllows(Group $group, string $ability, mixed $arguments): bool
    {
        return $group->has_documents
            && $this->user()->can($ability, $arguments);
    }

    /**
     * A Folder id that must name one of the given Group's Folders.
     */
    protected function folderOfGroup(int $groupId): Exists
    {
        return Rule::exists('document_folders', 'id')->where('group_id', $groupId);
    }

    /**
     * The message for a Folder action that would nest past {@see DocumentFolder::MAX_DEPTH}.
     */
    protected function tooDeepMessage(): string
    {
        return trans('document_folders.error.too_deep', ['max' => DocumentFolder::MAX_DEPTH]);
    }
}
