<?php

namespace App\Rules;

use App\Models\DocumentFolder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Folder name unique among its siblings (#714, ADR-0030 §3): the other Folders of the same
 * Group with the same parent, the top level included (the database's unique index never
 * compares null parents, so this rule is what holds top-level names unique). Compared under
 * the column's collation, so case and accents follow the database.
 */
class UniqueFolderName implements ValidationRule
{
    public function __construct(
        private readonly int $groupId,
        private readonly ?int $parentId,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = DocumentFolder::query()
            ->where('group_id', $this->groupId)
            ->where('parent_id', $this->parentId)
            ->where('name', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($taken) {
            $fail(trans('document_folders.error.name_taken'));
        }
    }
}
