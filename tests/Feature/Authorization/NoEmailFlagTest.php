<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Database\Eloquent\MassAssignmentException;

/*
 * The no-email flag (#483, ADR-0024 §9). One Records-set switch on a Member that
 * silences every mail. Like super-tier, it is never a field on a member form: not
 * mass-assignable, flipped only through a dedicated, separately-gated action. These
 * tests prove the hardening and the gate — Records and super-tier may switch it; a
 * Chair or the Member themself may not.
 */

/** A member holding member-administration authority via the Records stewardship. */
function noEmailRecordsOfficer(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()
        ->stewarding(StewardshipFunction::MemberAdmin)
        ->create(['group_id' => $records->id]);

    $officer = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $officer->id]);

    return $officer;
}

/** A member holding the given role in the given Group. */
function noEmailChairOf(Group $group): Member
{
    $chair = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $chair->id]);
    GroupMemberRole::factory()->role(Role::Chair)->create(['group_member_id' => $membership->id]);

    return $chair;
}

// --- Mass-assignment hardening ----------------------------------------------

it('does not allow no_email to be mass-assigned', function () {
    $member = Member::factory()->create();

    expect(fn () => $member->fill(['no_email' => true]))
        ->toThrow(MassAssignmentException::class);

    expect($member->fresh()->no_email)->toBeFalse();
});

it('casts no_email to a boolean and defaults it off', function () {
    $member = Member::factory()->create();

    expect($member->fresh()->no_email)->toBeFalse();

    expect(Member::factory()->noEmail()->create()->fresh()->no_email)->toBeTrue();
});

// --- Dedicated set/clear action ---------------------------------------------

it('lets a Records officer set the no-email flag on a member', function () {
    $target = Member::factory()->create();

    $this->actingAs(noEmailRecordsOfficer())
        ->put(route('members.no-email-flag.update', $target), ['no_email' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->no_email)->toBeTrue();
});

it('lets a super-tier member clear the no-email flag on a member', function () {
    $target = Member::factory()->noEmail()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put(route('members.no-email-flag.update', $target), ['no_email' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->no_email)->toBeFalse();
});

it('forbids a Chair from setting the no-email flag', function () {
    $group = Group::factory()->create();
    $target = Member::factory()->create();

    $this->actingAs(noEmailChairOf($group))
        ->put(route('members.no-email-flag.update', $target), ['no_email' => true])
        ->assertForbidden();

    expect($target->fresh()->no_email)->toBeFalse();
});

it('forbids the member themselves from setting their own no-email flag', function () {
    $member = Member::factory()->create();

    $this->actingAs($member)
        ->put(route('members.no-email-flag.update', $member), ['no_email' => true])
        ->assertForbidden();

    expect($member->fresh()->no_email)->toBeFalse();
});

it('redirects an unauthenticated request to login', function () {
    $target = Member::factory()->create();

    $this->put(route('members.no-email-flag.update', $target), ['no_email' => true])
        ->assertRedirect(route('login'));
});
