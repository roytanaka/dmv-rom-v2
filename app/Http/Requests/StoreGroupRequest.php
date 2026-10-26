<?php

namespace App\Http\Requests;

use App\Enums\Kind;
use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a Group from DMV Settings (#842, ADR-0036 §4): four fields, the rest defaults set by
 * the controller. The slug is set here only (the structure form never changes it), so it must
 * be URL-safe and unused. The parent is any active Group but the root: a Group always sits
 * under one of the root's children (#850, ADR-0036 §4), so a crafted root parent is refused too.
 *
 * Authorized on the Group-less `administer-groups` ability: only the super-tier passes.
 */
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer-groups');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:groups,slug'],
            'parent_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $parent = Group::query()->active()->whereKey($value)->first();
                if ($parent === null) {
                    $fail('dmv_settings.error.inactive_group')->translate();
                } elseif ($parent->isRoot()) {
                    $fail('dmv_settings.error.root_parent')->translate();
                }
            }],
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
        ];
    }
}
