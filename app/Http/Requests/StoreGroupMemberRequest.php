<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Rules\CapabilityValidRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a member to a Group's roster (#192, PRD #186). The mutation is authorized
 * structurally here — `authorize()` resolves the route-bound Group and delegates to
 * the GroupMemberPolicy, never returning `true` blindly — and `rules()` is a
 * whitelist of the membership fields plus any roles to assign on add. The controller
 * passes `validated()`, never `all()`.
 */
class StoreGroupMemberRequest extends FormRequest
{
    /**
     * Authorize against the GroupMemberPolicy: the actor must be able to add a member
     * to the route-bound Group (Secretary / Chair / super-tier). Never on the root DMV
     * Group: its membership is derived from Category and no row is ever stored there
     * (ADR-0036 §9). That refusal sits here, outside the Gate verdict, because the
     * super-tier `Gate::before` short-circuit would otherwise grant it.
     */
    public function authorize(): bool
    {
        $group = $this->route('group');

        return ! $group->isRoot()
            && $this->user()->can('create', [GroupMember::class, $group]);
    }

    /**
     * The whitelist of fields adding members may set. `member_ids` are the Members
     * brought in (#873) — each required to already exist and not already be in this
     * Group, so one bad ID rejects the whole batch. `status` defaults to Full in the
     * controller when omitted and applies to every Member. `roles` are assigned on add —
     * each capability-valid for this Group (a role the Group has no capability for is
     * rejected, never surfaced as a 500), at most one of each — and only when one Member
     * is added: officers assign roles one person at a time.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('members', 'id'),
                Rule::unique('group_member', 'member_id')->where('group_id', $group->id),
            ],
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
            'roles' => ['sometimes', 'array', Rule::prohibitedIf(fn () => count((array) $this->input('member_ids', [])) > 1)],
            'roles.*' => ['required', Rule::enum(Role::class), 'distinct', new CapabilityValidRole($group)],
        ];
    }

    /**
     * Plain-language errors for the batch: the dialog shows one line, not a per-index
     * message naming `member_ids.2`.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_ids.*.exists' => __('group.roster.add_unknown'),
            'member_ids.*.unique' => __('group.roster.add_already_member'),
            'roles.prohibited' => __('group.roster.add_roles_one_member'),
        ];
    }
}
