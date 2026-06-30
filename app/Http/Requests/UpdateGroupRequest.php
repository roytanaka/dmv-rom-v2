<?php

namespace App\Http\Requests;

use App\Enums\GroupBanner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Officer edits to a Group's Overview (#191, PRD #186): the inline About Us text
 * and the curated banner selection. The mutation is authorized structurally here —
 * `authorize()` resolves the bound Group and delegates to the GroupPolicy, never
 * returning `true` blindly — and `rules()` is a whitelist of exactly the two
 * editable fields. The controller passes `validated()`, never `all()`.
 */
class UpdateGroupRequest extends FormRequest
{
    /**
     * Authorize against the GroupPolicy: the actor must be able to update the
     * route-bound Group (Secretary / Chair / super-tier).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('group'));
    }

    /**
     * The whitelist of fields an officer edit may set. `description` is
     * member-authored About Us content (nullable — an officer may clear it);
     * `banner_key` must be one of the curated set, or null to fall back to the
     * neutral default.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:10000'],
            'banner_key' => ['nullable', Rule::enum(GroupBanner::class)],
        ];
    }
}
