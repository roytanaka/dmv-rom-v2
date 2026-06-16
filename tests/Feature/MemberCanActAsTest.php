<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/*
 * The per-(Member, Group) authority resolver (PRD #148, ADR-0017, slice #150).
 *
 * `canActAs` is the single predicate every gate and policy calls to answer "does
 * this member hold (effectively) this role in this Group?". It folds in
 * Chair-implication (Chair implies every officer role in its own Group except
 * Treasurer), matches group-wide role rows only, and never leaks authority
 * across Groups. Raw `holdsRole()` stays for literal "is an X" display checks.
 */

/**
 * Attach a role to a member within a group, creating the membership as needed.
 */
function grantRole(Member $member, Group $group, Role $role): void
{
    $membership = GroupMember::query()->firstOrCreate(
        ['group_id' => $group->id, 'member_id' => $member->id],
        ['status' => MembershipStatus::Full],
    );

    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
}

it('resolves true when the member literally holds the role', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->program()->create();
    grantRole($member, $group, Role::Scheduler);

    expect($member->fresh()->canActAs(Role::Scheduler, $group))->toBeTrue();
});

it('resolves false when the member is not in the Group at all', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();

    expect($member->canActAs(Role::Chair, $group))->toBeFalse();
});

it('lets a Chair act as every officer role in its own Group except Treasurer', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->program()->create();
    grantRole($member, $group, Role::Chair);
    $member = $member->fresh();

    foreach ([Role::Secretary, Role::Scheduler, Role::Statistician] as $implied) {
        expect($member->canActAs($implied, $group))->toBeTrue();
    }
});

it('does not let a Chair act as Treasurer without the explicit role', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    grantRole($member, $group, Role::Chair);

    expect($member->fresh()->canActAs(Role::Treasurer, $group))->toBeFalse();
});

it('lets a member who explicitly holds Treasurer act as Treasurer', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    grantRole($member, $group, Role::Treasurer);

    expect($member->fresh()->canActAs(Role::Treasurer, $group))->toBeTrue();
});

it('does not leak a role held in Group A into authority over Group B', function () {
    $member = Member::factory()->create();
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    grantRole($member, $groupA, Role::Chair);

    $member = $member->fresh();

    expect($member->canActAs(Role::Chair, $groupA))->toBeTrue()
        ->and($member->canActAs(Role::Chair, $groupB))->toBeFalse()
        ->and($member->canActAs(Role::Secretary, $groupB))->toBeFalse();
});

it('resolves authority for an eager-loaded member without per-call queries', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->program()->create();
    grantRole($member, $group, Role::Chair);

    $member = Member::with('memberships.roles')->find($member->id);

    DB::enableQueryLog();
    $member->canActAs(Role::Secretary, $group);
    $member->canActAs(Role::Scheduler, $group);
    $member->canActAs(Role::Treasurer, $group);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});
