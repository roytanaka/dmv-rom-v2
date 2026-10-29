<?php

namespace App\Rules;

use App\Models\Group;
use App\Models\Member;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A parent a parent officer may put a Group under (ADR-0036 §11): one that passes
 * `create-sub-group` (their own active Group or one below it, never the root or a Container),
 * else `outside_subtree`, an unknown id included. Then it must be active, unless it is
 * $currentParentId, the parent a Group already has, which it may keep. Creating a sub-group and
 * moving one refuse alike through this rule.
 */
class ParentInOfficerSubtree implements ValidationRule
{
    public function __construct(
        private readonly Member $actor,
        private readonly ?int $currentParentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parent = Group::query()->find($value);

        if ($parent === null || ! $this->actor->can('create-sub-group', $parent)) {
            $fail('dmv_settings.error.outside_subtree')->translate();
        } elseif ($parent->id !== $this->currentParentId && ! $parent->isActive()) {
            $fail('dmv_settings.error.inactive_group')->translate();
        }
    }
}
