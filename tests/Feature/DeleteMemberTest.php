<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\BoardPosition;
use App\Models\CategoryChange;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\HoursRecord;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\MemberLanguage;
use Illuminate\Support\Facades\Log;

/*
 * Delete (#921, ADR-0038 §6): on the Past, Deceased and Admin lists only. The member admin types
 * the Member's last name; a Member with Hours records is refused, since those are in the reported
 * totals. Otherwise the Member and their own rows go.
 */

function deleteMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create();
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

function deleteMemberRequest(Member $actor, Member $member, ?string $lastName = null)
{
    return test()->actingAs($actor)
        ->from('/officer/members/deceased')
        ->delete(route('members.destroy', $member), ['confirm_last_name' => $lastName ?? $member->last_name]);
}

it('deletes a Member on the Past, Deceased or Admin list with their own rows', function (array $attributes) {
    $admin = deleteMemberAdmin();
    $member = Member::factory()->create(['last_name' => 'Gone', ...$attributes]);
    $group = Group::factory()->create();
    $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    $member->languages()->create(['language_code' => 'fr', 'speaks' => true]);
    $member->boardPositions()->create(['position' => 'Treasurer', 'years' => '2010-2012']);
    $member->categoryChanges()->create(['category' => 'deceased', 'date' => '2020-01-01']);

    deleteMemberRequest($admin, $member)->assertRedirect('/officer/members/deceased')->assertSessionHasNoErrors();

    expect(Member::find($member->id))->toBeNull()
        ->and(GroupMember::where('member_id', $member->id)->exists())->toBeFalse()
        ->and(MemberLanguage::where('member_id', $member->id)->exists())->toBeFalse()
        ->and(BoardPosition::where('member_id', $member->id)->exists())->toBeFalse()
        ->and(CategoryChange::where('member_id', $member->id)->exists())->toBeFalse();
})->with([
    'past' => [['category' => Category::Resigned, 'personal_data_removed_at' => '2025-01-01 00:00:00']],
    'deceased' => [['category' => Category::Deceased]],
    'admin' => [['category' => Category::Admin]],
]);

it('writes one log line for a delete', function () {
    $admin = deleteMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Deceased]);
    Log::spy();

    deleteMemberRequest($admin, $member)->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context) => $context['member_id'] === $member->id
        && $context['actor_id'] === $admin->id);
});

it('refuses a Member with Hours records and names Remove personal data', function () {
    $admin = deleteMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Deceased]);
    HoursRecord::factory()->create(['member_id' => $member->id]);

    deleteMemberRequest($admin, $member)
        ->assertSessionHasErrors(['member' => __('records.delete.has_hours')]);

    expect(Member::find($member->id))->not->toBeNull();
});

it('refuses a Member who hosts an Invitation', function () {
    $admin = deleteMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Admin]);
    Invitation::factory()->create(['host_id' => $member->id]);

    deleteMemberRequest($admin, $member)->assertSessionHasErrors('member');

    expect(Member::find($member->id))->not->toBeNull();
});

it('requires the typed last name', function () {
    $admin = deleteMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Deceased, 'last_name' => 'Gone']);

    deleteMemberRequest($admin, $member, 'Wrong')->assertSessionHasErrors('confirm_last_name');
    deleteMemberRequest($admin, $member, '')->assertSessionHasErrors('confirm_last_name');

    expect(Member::find($member->id))->not->toBeNull();
});

it('never deletes a Member on another list, even for the super-tier', function (array $attributes) {
    $admin = deleteMemberAdmin();
    $super = Member::factory()->superTier()->create();
    $member = Member::factory()->create($attributes);

    deleteMemberRequest($admin, $member)->assertForbidden();
    deleteMemberRequest($super, $member)->assertForbidden();

    expect(Member::find($member->id))->not->toBeNull();
})->with([
    'current' => [['category' => Category::Active]],
    'resigned' => [['category' => Category::Resigned]],
    'donor' => [['category' => Category::Donor]],
]);

it('lets the super-tier delete a Deceased Member', function () {
    $super = Member::factory()->superTier()->create();
    $member = Member::factory()->create(['category' => Category::Deceased]);

    deleteMemberRequest($super, $member)->assertSessionHasNoErrors();

    expect(Member::find($member->id))->toBeNull();
});

it('forbids a Member without member administration', function () {
    $member = Member::factory()->create(['category' => Category::Deceased]);

    deleteMemberRequest(Member::factory()->create(), $member)->assertForbidden();

    expect(Member::find($member->id))->not->toBeNull();
});
