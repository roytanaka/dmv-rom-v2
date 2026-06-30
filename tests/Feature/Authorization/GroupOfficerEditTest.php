<?php

use App\Enums\GroupBanner;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Role-matrix HTTP harness for the first group-officer write path (#191, PRD #186):
 * the inline About Us edit and the curated banner selection on a Group's Overview.
 *
 * Exercises the edit through every layer — route → auth middleware → Form Request
 * authorize() → GroupPolicy → Gate::before — as an allow-and-deny matrix over the
 * spine factories. The edits are gated to a Secretary or Chair of the Group (plus
 * the super-tier); the deny rows (ordinary member, wrong-Group officer, non-member,
 * unauthenticated) prove the deny-by-default / fail-closed posture.
 */

/** A member holding the given role in the given Group. */
function groupOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** A member of the Group holding no role — an ordinary member. */
function plainMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

// --- About Us edit — allow rows --------------------------------------------

it('lets a Secretary edit the Group About Us', function () {
    $group = Group::factory()->create(['description' => 'Old.']);

    $this->actingAs(groupOfficerOf($group, Role::Secretary))
        ->patch(route('groups.update', $group), ['description' => 'New about text.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($group->fresh()->description)->toBe('New about text.');
});

it('lets a Chair edit the Group About Us (Chair-implication)', function () {
    $group = Group::factory()->create(['description' => 'Old.']);

    $this->actingAs(groupOfficerOf($group, Role::Chair))
        ->patch(route('groups.update', $group), ['description' => 'Chair edit.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($group->fresh()->description)->toBe('Chair edit.');
});

it('lets a super-tier member edit any Group About Us', function () {
    $group = Group::factory()->create(['description' => 'Old.']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch(route('groups.update', $group), ['description' => 'Super edit.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($group->fresh()->description)->toBe('Super edit.');
});

// --- Banner selection — allow rows -----------------------------------------

it('lets a Secretary pick a banner from the curated set', function () {
    $group = Group::factory()->create(['banner_key' => null]);

    $this->actingAs(groupOfficerOf($group, Role::Secretary))
        ->patch(route('groups.update', $group), ['banner_key' => GroupBanner::Quill->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($group->fresh()->banner_key)->toBe(GroupBanner::Quill);
});

it('lets an officer clear the banner back to the default', function () {
    $group = Group::factory()->create(['banner_key' => GroupBanner::Quill->value]);

    $this->actingAs(groupOfficerOf($group, Role::Chair))
        ->patch(route('groups.update', $group), ['banner_key' => null])
        ->assertSessionHasNoErrors();

    expect($group->fresh()->banner_key)->toBeNull();
});

it('rejects a banner key outside the curated set', function () {
    $group = Group::factory()->create();

    $this->actingAs(groupOfficerOf($group, Role::Secretary))
        ->patch(route('groups.update', $group), ['banner_key' => 'not-a-banner'])
        ->assertSessionHasErrors('banner_key');
});

// --- Deny rows (required) ---------------------------------------------------

it('forbids an ordinary member from editing the Overview', function () {
    $group = Group::factory()->create(['description' => 'Old.']);

    $this->actingAs(plainMemberOf($group))
        ->patch(route('groups.update', $group), ['description' => 'Hacked.'])
        ->assertForbidden();

    expect($group->fresh()->description)->toBe('Old.');
});

it('forbids an officer of another Group from editing this Group', function () {
    $group = Group::factory()->create(['description' => 'Old.']);
    $other = Group::factory()->create();

    $this->actingAs(groupOfficerOf($other, Role::Secretary))
        ->patch(route('groups.update', $group), ['description' => 'Cross-group.'])
        ->assertForbidden();

    expect($group->fresh()->description)->toBe('Old.');
});

it('forbids a non-member from editing the Overview', function () {
    $group = Group::factory()->create(['description' => 'Old.']);

    $this->actingAs(Member::factory()->create())
        ->patch(route('groups.update', $group), ['description' => 'Outsider.'])
        ->assertForbidden();

    expect($group->fresh()->description)->toBe('Old.');
});

it('redirects an unauthenticated edit to login', function () {
    $group = Group::factory()->create();

    $this->patch(route('groups.update', $group), ['description' => 'Anon.'])
        ->assertRedirect(route('login'));
});

// --- `can` hint drives the affordances (UI hint only) -----------------------

it('hints update on for an officer and off for an ordinary member', function () {
    $group = Group::factory()->create();

    $this->actingAs(groupOfficerOf($group, Role::Secretary))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('can.update', true));

    $this->actingAs(plainMemberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('can.update', false));
});

it('exposes the selected banner key on the Group payload', function () {
    $group = Group::factory()->create(['banner_key' => GroupBanner::Lattice->value]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('group.banner_key', GroupBanner::Lattice->value));
});
