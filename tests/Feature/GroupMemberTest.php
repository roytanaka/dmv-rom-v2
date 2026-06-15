<?php

use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Database\QueryException;

/*
 * Model-test pattern for the spine (PRD #126, slice 3 / #129).
 *
 * Asserts external behaviour and invariants through the GroupMember model's
 * public API — relationship round-trips, the (Group, Member) uniqueness
 * invariant, enum + LOA casts, illegal-value rejection, and the per-Group
 * membership helper — not column existence.
 */

it('round-trips memberships from both the Member and the Group', function () {
    $group = Group::factory()->create();
    $member = Member::factory()->create();

    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);

    expect($membership->fresh()->group->is($group))->toBeTrue()
        ->and($membership->fresh()->member->is($member))->toBeTrue()
        ->and($member->fresh()->memberships->pluck('id'))->toContain($membership->id)
        ->and($group->fresh()->memberships->pluck('id'))->toContain($membership->id);
});

it('rejects a second membership for the same Group and Member', function () {
    $group = Group::factory()->create();
    $member = Member::factory()->create();

    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
})->throws(QueryException::class);

it('lets the same Member belong to more than one Group', function () {
    $member = Member::factory()->create();
    $first = Group::factory()->create();
    $second = Group::factory()->create();

    GroupMember::factory()->create(['group_id' => $first->id, 'member_id' => $member->id]);
    GroupMember::factory()->create(['group_id' => $second->id, 'member_id' => $member->id]);

    expect($member->fresh()->memberships)->toHaveCount(2);
});

it('casts status to the MembershipStatus enum', function () {
    $membership = GroupMember::factory()->create(['status' => MembershipStatus::Trainee]);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Trainee);
});

it('rejects an illegal status value', function () {
    GroupMember::factory()->create(['status' => 'guest']);
})->throws(ValueError::class);

it('casts the LOA window to dates', function () {
    $membership = GroupMember::factory()->onLoa()->create();

    $fresh = $membership->fresh();

    expect($fresh->status)->toBe(MembershipStatus::Loa)
        ->and($fresh->loa_start)->not->toBeNull()
        ->and($fresh->loa_end)->not->toBeNull()
        ->and($fresh->loa_start->lessThan($fresh->loa_end))->toBeTrue();
});

it('leaves the LOA window null when no leave is scheduled', function () {
    $membership = GroupMember::factory()->create();

    expect($membership->fresh()->loa_start)->toBeNull()
        ->and($membership->fresh()->loa_end)->toBeNull();
});

it('keeps a Group\'s memberships when the Group is archived', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $group->update(['lifecycle_state' => LifecycleState::Archived]);

    expect($membership->fresh())->not->toBeNull()
        ->and($group->fresh()->memberships->pluck('id'))->toContain($membership->id);
});

it('fetches a Member\'s membership in a given Group', function () {
    $member = Member::factory()->create();
    $joined = Group::factory()->create();
    $other = Group::factory()->create();

    $membership = GroupMember::factory()->create([
        'group_id' => $joined->id,
        'member_id' => $member->id,
    ]);

    expect($member->membershipIn($joined)->is($membership))->toBeTrue()
        ->and($member->membershipIn($other))->toBeNull();
});
