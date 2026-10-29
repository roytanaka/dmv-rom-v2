<?php

namespace App\Rules;

use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Group name unique among its siblings (ADR-0036 §11): no other Group under the same parent,
 * archived ones included, has it. The same name under another parent is fine (Docents and
 * Gallery Interpreters may each have "Sections"). Compared under the column's collation, so
 * case and accents follow the database. Pass the Group being saved as $ignoreId so it never
 * clashes with itself.
 */
class UniqueSiblingGroupName implements ValidationRule
{
    public function __construct(
        private readonly ?int $parentId,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = Group::query()
            ->where('parent_id', $this->parentId)
            ->where('name', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($taken) {
            $fail(trans('dmv_settings.error.sibling_name_taken'));
        }
    }
}
