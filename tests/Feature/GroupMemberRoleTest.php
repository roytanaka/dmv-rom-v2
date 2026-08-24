<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Database\QueryException;

/*
 * Model-test pattern for the spine (PRD #126, slice 4 / #130).
 *
 * Asserts external behaviour and invariants through the role model's public API
 * — the membership↔roles round-trip, the (membership, role) uniqueness
 * invariant, the enum cast + illegal-value rejection, the write-time
 * capability-gating invariant, and the "holds role R in Group G" helper — not
 * column existence.
 */

it('round-trips roles from the membership', function () {
    $membership = GroupMember::factory()->create();

    $role = GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Chair,
    ]);

    expect($role->fresh()->groupMember->is($membership))->toBeTrue()
        ->and($membership->fresh()->roles->pluck('id'))->toContain($role->id);
});

it('lets one membership carry several roles', function () {
    $group = Group::factory()->program()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Scheduler]);

    expect($membership->fresh()->roles)->toHaveCount(2);
});

it('rejects the same role twice on one membership', function () {
    $membership = GroupMember::factory()->create();

    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);
})->throws(QueryException::class);

it('casts role to the Role enum', function () {
    $membership = GroupMember::factory()->create();

    $role = GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Secretary,
    ]);

    expect($role->fresh()->role)->toBe(Role::Secretary);
});

it('rejects an illegal role value', function () {
    GroupMemberRole::factory()->create(['role' => 'overlord']);
})->throws(ValueError::class);

it('attaches a capability-backed role when the Group has the flag on', function () {
    $group = Group::factory()->program()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $role = GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Scheduler,
    ]);

    expect($role->fresh()->role)->toBe(Role::Scheduler);
});

it('rejects a capability-backed role when the Group lacks the flag', function () {
    $group = Group::factory()->standingCommittee()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Scheduler,
    ]);
})->throws(DomainException::class);

it('maps the news-editor role to the announcements capability', function () {
    expect(Role::NewsEditor->requiredCapability())->toBe('has_announcements');
});

it('attaches the news-editor role when the Group has announcements on', function () {
    $group = Group::factory()->create(['has_announcements' => true]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $role = GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::NewsEditor,
    ]);

    expect($role->fresh()->role)->toBe(Role::NewsEditor);
});

it('rejects the news-editor role when the Group lacks announcements', function () {
    $group = Group::factory()->create(['has_announcements' => false]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::NewsEditor,
    ]);
})->throws(DomainException::class);

it('treats Statistician as a core role with no required capability (ADR-0022 §3)', function () {
    expect(Role::Statistician->requiredCapability())->toBeNull();
});

it('attaches the Statistician role to a Group that runs no scheduling and no hours flag', function () {
    // A plain standing committee — no capability flags on. Since ADR-0022 withdraws
    // the hours capability, Statistician joins the core roles and attaches anywhere.
    $group = Group::factory()->standingCommittee()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $role = GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Statistician,
    ]);

    expect($role->fresh()->role)->toBe(Role::Statistician);
});

it('attaches core roles regardless of capability flags', function () {
    $group = Group::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    foreach ([Role::Chair, Role::Secretary, Role::Treasurer] as $core) {
        GroupMemberRole::factory()->create([
            'group_member_id' => $membership->id,
            'role' => $core,
        ]);
    }

    expect($membership->fresh()->roles->pluck('role'))
        ->toContain(Role::Chair, Role::Secretary, Role::Treasurer);
});

it('answers whether a Member holds a role in a Group', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->program()->create();
    $other = Group::factory()->create();

    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);
    GroupMemberRole::factory()->create([
        'group_member_id' => $membership->id,
        'role' => Role::Scheduler,
    ]);

    expect($member->holdsRole(Role::Scheduler, $group))->toBeTrue()
        ->and($member->holdsRole(Role::Chair, $group))->toBeFalse()
        ->and($member->holdsRole(Role::Scheduler, $other))->toBeFalse();
});
