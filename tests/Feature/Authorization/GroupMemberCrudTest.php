<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;

/*
 * Role-matrix HTTP harness for officer roster CRUD (#192, PRD #186).
 *
 * Exercises add / change-standing / assign-revoke-role / resign / hard-remove through
 * every layer — route → auth middleware → Form Request authorize() → GroupMemberPolicy
 * → Gate::before. Roster management is gated to a Group's Secretary | Chair |
 * super-tier (no capability guard — every Group has a roster); everyone else is
 * denied. The deny rows (ordinary member, officer of another Group, unauthenticated)
 * prove the fail-closed posture; the capability-invalid-role rejection and the
 * dependent-records hard-remove block round it out.
 */

/** A member of the given Group holding the given role. */
function crudOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function crudMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

// --- Adding a member (store) — allow rows -----------------------------------

it('lets a Secretary add a member, defaulting standing to Full', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->post(route('group-members.store', $group), ['member_id' => $target->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $membership = GroupMember::where('member_id', $target->id)->sole();
    expect($membership->group_id)->toBe($group->id)
        ->and($membership->status)->toBe(MembershipStatus::Full);
});

it('lets a Chair add a member (Chair-implication)', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(crudOfficerOf($group, Role::Chair))
        ->post(route('group-members.store', $group), ['member_id' => $target->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(GroupMember::where('member_id', $target->id)->exists())->toBeTrue();
});

it('lets a super-tier member add to any Group', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('group-members.store', $group), ['member_id' => $target->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(GroupMember::where('member_id', $target->id)->exists())->toBeTrue();
});

it('adds a member with an explicit standing and roles', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->post(route('group-members.store', $group), [
            'member_id' => $target->id,
            'status' => MembershipStatus::Trainee->value,
            'roles' => [Role::Secretary->value],
        ])
        ->assertSessionHasNoErrors();

    $membership = GroupMember::where('member_id', $target->id)->sole();
    expect($membership->status)->toBe(MembershipStatus::Trainee)
        ->and($membership->roles->pluck('role')->all())->toBe([Role::Secretary]);
});

// --- Adding a member (store) — deny + validation rows -----------------------

it('forbids an ordinary member from adding to the roster', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(crudMemberOf($group))
        ->post(route('group-members.store', $group), ['member_id' => $target->id])
        ->assertForbidden();

    expect(GroupMember::where('member_id', $target->id)->exists())->toBeFalse();
});

it('forbids a Secretary of one Group from adding to another', function () {
    $secretary = crudOfficerOf(Group::factory()->create(), Role::Secretary);
    $other = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs($secretary)
        ->post(route('group-members.store', $other), ['member_id' => $target->id])
        ->assertForbidden();

    expect(GroupMember::where('group_id', $other->id)->exists())->toBeFalse();
});

it('redirects an unauthenticated add to login', function () {
    $group = Group::factory()->create();

    $this->post(route('group-members.store', $group), ['member_id' => Member::factory()->create()->id])
        ->assertRedirect(route('login'));
});

it('rejects adding a role the Group has no capability for', function () {
    // A plain Group has every capability off, so a Scheduler role (needs scheduling)
    // is offered nowhere and rejected here with a clean validation error, not a 500.
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->post(route('group-members.store', $group), [
            'member_id' => $target->id,
            'roles' => [Role::Scheduler->value],
        ])
        ->assertSessionHasErrors('roles.0');

    expect(GroupMember::where('member_id', $target->id)->exists())->toBeFalse();
});

it('rejects adding a member who is already in the Group', function () {
    $group = Group::factory()->create();
    $existing = crudMemberOf($group);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->post(route('group-members.store', $group), ['member_id' => $existing->id])
        ->assertSessionHasErrors('member_id');

    expect(GroupMember::where('member_id', $existing->id)->count())->toBe(1);
});

// --- Changing standing (update) ---------------------------------------------

it('lets a Secretary change a member standing', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Inactive);
});

it('puts a member on a leave window via the LOA columns', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), [
            'status' => MembershipStatus::Loa->value,
            'loa_start' => '2026-07-01',
            'loa_end' => '2026-09-01',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $membership->fresh();
    expect($fresh->status)->toBe(MembershipStatus::Loa)
        ->and($fresh->loa_start->toDateString())->toBe('2026-07-01')
        ->and($fresh->loa_end->toDateString())->toBe('2026-09-01');
});

it('rejects a leave window that ends before it starts', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), [
            'loa_start' => '2026-09-01',
            'loa_end' => '2026-07-01',
        ])
        ->assertSessionHasErrors('loa_end');
});

it('forbids an ordinary member from changing standing', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id]);

    $this->actingAs(crudMemberOf($group))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertForbidden();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Full);
});

it('forbids an officer of another Group from changing standing', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf(Group::factory()->create(), Role::Secretary))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertForbidden();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Full);
});

it('redirects an unauthenticated standing change to login', function () {
    $membership = GroupMember::factory()->create();

    $this->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertRedirect(route('login'));
});

// --- Assigning / revoking roles (update) ------------------------------------

it('assigns roles to a membership', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Chair))
        ->patch(route('group-members.update', $membership), ['roles' => [Role::Secretary->value]])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles->pluck('role')->all())->toBe([Role::Secretary]);
});

it('revokes roles by sending an empty set', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);
    $membership->roles()->create(['role' => Role::Secretary]);

    $this->actingAs(crudOfficerOf($group, Role::Chair))
        ->patch(route('group-members.update', $membership), ['roles' => []])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles)->toHaveCount(0);
});

it('keeps roles untouched on a standing-only edit', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);
    $membership->roles()->create(['role' => Role::Secretary]);

    $this->actingAs(crudOfficerOf($group, Role::Chair))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles->pluck('role')->all())->toBe([Role::Secretary]);
});

it('rejects assigning a role the Group has no capability for', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), ['roles' => [Role::Scheduler->value]])
        ->assertSessionHasErrors('roles.0');

    expect($membership->fresh()->roles)->toHaveCount(0);
});

it('assigns a capability-backed role when the Group runs that capability', function () {
    $group = Group::factory()->program()->create(); // scheduling on
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), ['roles' => [Role::Scheduler->value]])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles->pluck('role')->all())->toBe([Role::Scheduler]);
});

// --- Resign (soft remove) ---------------------------------------------------

it('resigns a member, keeping the row and its history', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Resigned->value])
        ->assertSessionHasNoErrors();

    expect($membership->fresh())->not->toBeNull()
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Resigned);
});

// --- Hard-remove (true delete) ----------------------------------------------

it('lets a Secretary hard-remove a membership with no dependent records', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf($group, Role::Secretary))
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(GroupMember::find($membership->id))->toBeNull();
});

it('blocks a hard-remove when the membership has dependent records', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);
    $membership->roles()->create(['role' => Role::Secretary]);

    $this->actingAs(crudOfficerOf($group, Role::Chair))
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasErrors('membership');

    expect(GroupMember::find($membership->id))->not->toBeNull();
});

it('blocks even a super-tier hard-remove when dependent records exist', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);
    $membership->roles()->create(['role' => Role::Secretary]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasErrors('membership');

    expect(GroupMember::find($membership->id))->not->toBeNull();
});

it('forbids an ordinary member from hard-removing a membership', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudMemberOf($group))
        ->delete(route('group-members.destroy', $membership))
        ->assertForbidden();

    expect(GroupMember::find($membership->id))->not->toBeNull();
});

it('forbids an officer of another Group from hard-removing a membership', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs(crudOfficerOf(Group::factory()->create(), Role::Secretary))
        ->delete(route('group-members.destroy', $membership))
        ->assertForbidden();

    expect(GroupMember::find($membership->id))->not->toBeNull();
});

it('redirects an unauthenticated hard-remove to login', function () {
    $membership = GroupMember::factory()->create();

    $this->delete(route('group-members.destroy', $membership))
        ->assertRedirect(route('login'));
});
