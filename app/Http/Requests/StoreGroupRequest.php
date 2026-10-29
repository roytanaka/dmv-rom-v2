<?php

namespace App\Http\Requests;

use App\Enums\Kind;
use App\Enums\MembershipStatus;
use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create a Group from DMV Settings. Two shapes on one route:
 *
 * - The super-tier (#842, ADR-0036 §4): four fields, the rest defaults set by the controller.
 *   The slug is set here only (the structure form never changes it), so it must be URL-safe
 *   and unused. The parent is any active Group but the root: a Group always sits under one of
 *   the root's children (#850, ADR-0036 §4), so a crafted root parent is refused too.
 *   Authorized on the Group-less `administer-groups` ability: only the super-tier passes.
 * - A parent officer (#884, ADR-0036 §11): a name and a parent only. The parent must pass
 *   `create-sub-group` (their own Group or one below it, never the root or a Container), else
 *   403. Siblings under one parent have unique names. The server makes the slug; a slug, kind
 *   or flag in a crafted request is not validated, so never reaches the Group. An optional Chair
 *   (#885) must be on the parent's active roster (a standing that counts as belonging).
 */
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->scoped()) {
            return true;
        }

        $parent = Group::query()->find($this->integer('parent_id'));

        return $parent !== null && Gate::allows('create-sub-group', $parent);
    }

    /** True for a parent officer's create, false for the super-tier's. */
    public function scoped(): bool
    {
        return ! Gate::allows('administer-groups');
    }

    /** The parent the new Group goes under. Call after validation. */
    public function parent(): Group
    {
        return Group::query()->findOrFail($this->validated('parent_id'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $parentRule = ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
            $parent = Group::query()->active()->whereKey($value)->first();
            if ($parent === null) {
                $fail('dmv_settings.error.inactive_group')->translate();
            } elseif ($parent->isRoot()) {
                $fail('dmv_settings.error.root_parent')->translate();
            }
        }];

        if ($this->scoped()) {
            return [
                'name' => ['required', 'string', 'max:255', Rule::unique('groups', 'name')->where('parent_id', $this->integer('parent_id'))],
                'parent_id' => $parentRule,
                'chair_id' => ['nullable', 'integer', Rule::exists('group_member', 'member_id')
                    ->where('group_id', $this->integer('parent_id'))
                    ->whereIn('status', MembershipStatus::belongingValues())],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:groups,slug'],
            'parent_id' => $parentRule,
            // A Container sits under the root, so only the seeder makes one (#850).
            'kind' => ['required', Rule::enum(Kind::class)->except(Kind::Container)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => __('dmv_settings.error.slug_taken'),
            'slug.regex' => __('dmv_settings.error.slug_format'),
            'name.unique' => __('dmv_settings.error.sibling_name_taken'),
            'chair_id.exists' => __('dmv_settings.error.chair_not_on_parent'),
        ];
    }
}
