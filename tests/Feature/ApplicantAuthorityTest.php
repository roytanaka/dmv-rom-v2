<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

/*
 * Applicant authority (#927, ADR-0038 §2): intake is run by the Group holding the `applicants`
 * Stewardship. Any member of the holder has applicant authority; its Chair is the Applicant
 * Chair. The `administer-applicants` Gate passes them and the super-tier, nobody else.
 */

/** The holder Group (Membership) and another Group; returns both. */
function applicantAuthorityOrg(): array
{
    $root = Group::factory()->standingCommittee()->create(['slug' => 'dmv', 'name' => 'DMV']);
    $membership = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'membership', 'name' => 'Membership']);
    $other = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'awards', 'name' => 'Awards']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::Applicants)->create(['group_id' => $membership->id]);

    return [$membership, $other];
}

function applicantAuthorityMember(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    if ($role !== null) {
        $membership->roles()->create(['role' => $role]);
    }

    return $member;
}

it('gives a plain member of the holder applicant authority, but not the Chair', function () {
    [$membership] = applicantAuthorityOrg();
    $member = applicantAuthorityMember($membership);

    expect($member->hasApplicantAuthority())->toBeTrue()
        ->and($member->isApplicantChair())->toBeFalse()
        ->and(Gate::forUser($member)->allows('administer-applicants'))->toBeTrue();
});

it('makes the Chair of the holder the Applicant Chair', function () {
    [$membership] = applicantAuthorityOrg();
    $chair = applicantAuthorityMember($membership, Role::Chair);

    expect($chair->hasApplicantAuthority())->toBeTrue()
        ->and($chair->isApplicantChair())->toBeTrue()
        ->and(Gate::forUser($chair)->allows('administer-applicants'))->toBeTrue();
});

it('gives a Chair of another Group no applicant authority', function () {
    [, $other] = applicantAuthorityOrg();
    $chair = applicantAuthorityMember($other, Role::Chair);

    expect($chair->hasApplicantAuthority())->toBeFalse()
        ->and($chair->isApplicantChair())->toBeFalse()
        ->and(Gate::forUser($chair)->allows('administer-applicants'))->toBeFalse();
});

it('passes the super-tier through the Gate', function () {
    applicantAuthorityOrg();

    expect(Gate::forUser(Member::factory()->superTier()->create())->allows('administer-applicants'))->toBeTrue();
});

it('gives nobody authority when no Group holds the stewardship', function () {
    $group = Group::factory()->create();
    $chair = applicantAuthorityMember($group, Role::Chair);

    expect($chair->hasApplicantAuthority())->toBeFalse()
        ->and($chair->isApplicantChair())->toBeFalse();
});

it('follows the stewardship when it moves', function () {
    [$membership, $other] = applicantAuthorityOrg();
    $member = applicantAuthorityMember($membership);
    $newcomer = applicantAuthorityMember($other);

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/applicants', ['group_id' => $other->id])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->hasApplicantAuthority())->toBeFalse()
        ->and($newcomer->fresh()->hasApplicantAuthority())->toBeTrue();
});
