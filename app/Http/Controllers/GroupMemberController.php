<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\DeleteGroupMemberRequest;
use App\Http\Requests\StoreGroupMemberRequest;
use App\Http\Requests\UpdateGroupMemberRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use Illuminate\Http\RedirectResponse;

/**
 * Officer roster CRUD (#192, PRD #186) — the write seam for a Group's membership
 * roster, parallel to the read surface on {@see GroupController::show}. Every
 * mutation is structurally authorized in its Form Request, which delegates to the
 * GroupMemberPolicy (a Secretary or Chair of the Group, plus the super-tier). The
 * `can` props on the Roster tab are UI hints only — the server enforces here.
 *
 * "Removing" a member has two shapes: a resign — the default — is a soft status
 * change handled by {@see update()} (status set to Resigned, keeping the row and its
 * history); a hard-remove is the true row delete in {@see destroy()}, reserved for
 * the added-in-error case where no dependent records exist.
 */
class GroupMemberController extends Controller
{
    /**
     * Add a member to a Group. The owning Group comes from the route; the Member,
     * standing, and any roles come from the Form Request whitelist. Standing defaults
     * to Full when the request omits it. Roles assigned on add are written alongside.
     */
    public function store(StoreGroupMemberRequest $request, Group $group): RedirectResponse
    {
        $data = $request->validated();

        $membership = $group->memberships()->create([
            'member_id' => $data['member_id'],
            'status' => $data['status'] ?? MembershipStatus::Full->value,
        ]);

        $this->syncRoles($membership, $data['roles'] ?? []);

        return back();
    }

    /**
     * Change a membership — its standing, leave window, and/or roles. A resign is
     * this edit with `status` set to Resigned. Roles are synced wholesale only when
     * the request carries them, so a standing-only edit never silently drops them.
     */
    public function update(UpdateGroupMemberRequest $request, GroupMember $membership): RedirectResponse
    {
        $data = $request->validated();
        $hasRoles = array_key_exists('roles', $data);
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $membership->update($data);

        if ($hasRoles) {
            $this->syncRoles($membership, $roles);
        }

        return back();
    }

    /**
     * Hard-remove a membership — the added-in-error case. The Form Request has
     * already confirmed there are no dependent records; the row is deleted outright.
     */
    public function destroy(DeleteGroupMemberRequest $request, GroupMember $membership): RedirectResponse
    {
        $membership->delete();

        return back();
    }

    /**
     * Replace a membership's roles with the supplied set — the simplest sync for the
     * small officer-role set, where each save sends the complete intended state. The
     * capability-gating invariant on {@see GroupMemberRole} still guards
     * each write, the validation rule having already failed-closed on a bad role.
     *
     * @param  list<string>  $roles
     */
    private function syncRoles(GroupMember $membership, array $roles): void
    {
        $membership->roles()->delete();

        foreach ($roles as $role) {
            $membership->roles()->create(['role' => $role]);
        }
    }
}
