<?php

use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;

/*
 * Model-test pattern for the spine (PRD #126, slice 5 / #131).
 *
 * Asserts external behaviour and invariants through the public model API —
 * the stewardship relationship, enum cast and illegal-value rejection, the
 * per-function steward lookup, the all-DMV helper, and the member-admin-authority
 * helper (the inputs a later ADR-0011 authorization PRD reads) — not column
 * existence.
 */

it('rounds-trips a Group and the functions it stewards', function () {
    $group = Group::factory()->create();
    $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    $group->stewardships()->create(['function' => StewardshipFunction::Statistics]);

    expect($group->fresh()->stewardships->pluck('function'))
        ->toContain(StewardshipFunction::MemberAdmin, StewardshipFunction::Statistics);
});

it('casts function to the StewardshipFunction enum', function () {
    $stewardship = GroupStewardship::factory()->stewarding(StewardshipFunction::Website)->create();

    expect($stewardship->fresh()->function)->toBe(StewardshipFunction::Website);
});

it('rejects an illegal function value', function () {
    GroupStewardship::factory()->create(['function' => 'newsletter']);
})->throws(ValueError::class);

it('returns the Group that stewards member_admin', function () {
    $records = Group::factory()->create(['name' => 'Records Group']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    Group::factory()->create();

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)?->is($records))->toBeTrue();
});

it('generalizes the steward lookup per function', function () {
    $stats = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::Statistics)->create(['group_id' => $stats->id]);

    expect(Group::stewardOf(StewardshipFunction::Statistics)?->is($stats))->toBeTrue()
        ->and(Group::stewardOf(StewardshipFunction::Website))->toBeNull();
});

it('answers whether a Member is all-DMV via super_tier', function () {
    $ordinary = Member::factory()->create();
    $allDmv = Member::factory()->superTier()->create();

    expect($ordinary->isAllDmv())->toBeFalse()
        ->and($allDmv->isAllDmv())->toBeTrue();
});

it('grants member-admin authority to a member of the Records-stewarding Group', function () {
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);

    $steward = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $steward->id]);

    expect($steward->hasMemberAdminAuthority())->toBeTrue();
});

it('withholds member-admin authority from a non-member of the Records-stewarding Group', function () {
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);

    $other = Group::factory()->create();
    $outsider = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $other->id, 'member_id' => $outsider->id]);

    expect($outsider->hasMemberAdminAuthority())->toBeFalse();
});

it('withholds member-admin authority when no Group stewards it', function () {
    $records = Group::factory()->create();
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $member->id]);

    expect($member->hasMemberAdminAuthority())->toBeFalse();
});
