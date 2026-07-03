<?php

namespace App\Http\Requests\Settings;

use App\Models\Skill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkillsUpdateRequest extends FormRequest
{
    /**
     * Authorize the self-service edit against the MemberPolicy (ADR-0017): the actor
     * is editing their own skills, so this resolves to the self branch — a Member
     * edits only their own selections (PRD #243). Explicit, not a blind `return true`.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only ids from the *active* catalog — an active skill whose category is also
     * active — are accepted, so a submitted unknown, retired, or inactive-category
     * skill id is rejected (no partial write). The allowlist is computed from the
     * catalog rather than a bare `exists` so the category-active constraint is
     * enforced in one place.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $selectable = Skill::query()
            ->active()
            ->whereHas('category', fn ($query) => $query->where('active', true))
            ->pluck('id')
            ->all();

        return [
            'skills' => ['nullable', 'array'],
            'skills.*' => [Rule::in($selectable)],
        ];
    }
}
