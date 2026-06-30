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
     * to the route-bound Group (Secretary / Chair / super-tier).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [GroupMember::class, $this->route('group')]);
    }

    /**
     * The whitelist of fields adding a member may set. `member_id` is the Member
     * brought in — required to already exist and not already be in this Group.
     * `status` defaults to Full in the controller when omitted. `roles` are assigned
     * on add — each capability-valid for this Group (a role the Group has no
     * capability for is rejected, never surfaced as a 500), at most one of each.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id'),
                Rule::unique('group_member', 'member_id')->where('group_id', $group->id),
            ],
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['required', Rule::enum(Role::class), 'distinct', new CapabilityValidRole($group)],
        ];
    }
}
