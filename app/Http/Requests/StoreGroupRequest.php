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
 * be URL-safe and unused. The parent is any active Group, the root included.
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
                if (! Group::query()->active()->whereKey($value)->exists()) {
                    $fail('dmv_settings.error.inactive_group')->translate();
                }
            }],
            'kind' => ['required', Rule::enum(Kind::class)],
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
