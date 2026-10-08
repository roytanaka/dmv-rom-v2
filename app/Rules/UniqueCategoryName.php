<?php

namespace App\Rules;

use App\Models\DocumentCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Document category name unique within one owning Folder's list (#724, ADR-0030 §4), the
 * library root's included (the database's unique index never compares null Folders, so this
 * rule is what holds root names unique). Compared under the column's collation, so case and
 * accents follow the database.
 */
class UniqueCategoryName implements ValidationRule
{
    public function __construct(
        private readonly int $groupId,
        private readonly ?int $folderId,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = DocumentCategory::query()
            ->where('group_id', $this->groupId)
            ->where('folder_id', $this->folderId)
            ->where('name', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($taken) {
            $fail(trans('document_categories.error.name_taken'));
        }
    }
}
