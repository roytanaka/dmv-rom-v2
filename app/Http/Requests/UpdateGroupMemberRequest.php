<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Rules\CapabilityValidRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing a membership (#192, PRD #186) — its standing (including a leave window),
 * or its assigned roles. Resign is the default "remove": this same edit with
 * `status` set to Resigned, which keeps the row and its history. The mutation is
 * authorized structurally here, delegating to the GroupMemberPolicy (an officer of
 * the owning Group, or the super-tier); `rules()` is a field whitelist and the
 * controller passes `validated()`.
 */
class UpdateGroupMemberRequest extends FormRequest
{
    /**
     * Authorize against the GroupMemberPolicy: the actor must be able to update the
     * route-bound membership (its owning Group's Secretary / Chair / super-tier).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('membership'));
    }

    /**
     * The whitelist of fields a roster edit may set. `status` is the within-Group
     * standing (Resigned for a resign). `loa_start` / `loa_end` are the optional
     * leave window, the end never before the start. `roles` — when present — replace
     * the membership's roles wholesale; each must be capability-valid for the Group.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('membership')->group;

        return [
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
            'loa_start' => ['nullable', 'date'],
            'loa_end' => ['nullable', 'date', 'after_or_equal:loa_start'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['required', Rule::enum(Role::class), 'distinct', new CapabilityValidRole($group)],
        ];
    }
}
