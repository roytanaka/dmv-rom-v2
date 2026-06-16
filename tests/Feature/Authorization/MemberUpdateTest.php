<?php

use App\Enums\Category;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;

/*
 * Role-matrix HTTP harness for the authorization tracer (#151, ADR-0017).
 *
 * Exercises one real ability — member-profile edit — through every layer (route →
 * auth middleware → Form Request authorize() → MemberPolicy → Gate::before), as a
 * per-action allow-and-deny matrix over the spine factories. The deny rows
 * (other non-officer, wrong-Group officer, unauthenticated) are required, not
 * optional: they prove the deny-by-default / fail-closed posture.
 */

/** A member holding member-administration authority via the Records stewardship. */
function recordsOfficer(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()
        ->stewarding(StewardshipFunction::MemberAdmin)
        ->create(['group_id' => $records->id]);

    $officer = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $officer->id]);

    return $officer;
}

/** A Chair of some ordinary Group — an officer, but with no reach outside it. */
function chairOfOwnGroup(): Member
{
    $officer = Member::factory()->create();
    $membership = GroupMember::factory()->create(['member_id' => $officer->id]);
    GroupMemberRole::factory()->role(Role::Chair)->create(['group_member_id' => $membership->id]);

    return $officer;
}

$payload = ['name' => 'Edited Name', 'email' => 'edited@example.com'];

// --- Allow rows -------------------------------------------------------------

it('lets a member edit their own profile', function () use ($payload) {
    $member = Member::factory()->create();

    $this->actingAs($member)
        ->patch(route('members.update', $member), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($member->fresh()->name)->toBe('Edited Name');
});

it('lets a Records officer edit another member', function () use ($payload) {
    $target = Member::factory()->create();

    $this->actingAs(recordsOfficer())
        ->patch(route('members.update', $target), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->name)->toBe('Edited Name');
});

it('lets a super-tier member edit another member', function () use ($payload) {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch(route('members.update', $target), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->name)->toBe('Edited Name');
});

// --- Deny rows (required) ---------------------------------------------------

it('forbids a non-officer member from editing another member', function () use ($payload) {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->patch(route('members.update', $target), $payload)
        ->assertForbidden();

    expect($target->fresh()->name)->not->toBe('Edited Name');
});

it('forbids an officer of an unrelated Group from editing a member', function () use ($payload) {
    $target = Member::factory()->create();

    $this->actingAs(chairOfOwnGroup())
        ->patch(route('members.update', $target), $payload)
        ->assertForbidden();

    expect($target->fresh()->name)->not->toBe('Edited Name');
});

it('redirects an unauthenticated request to login', function () use ($payload) {
    $target = Member::factory()->create();

    $this->patch(route('members.update', $target), $payload)
        ->assertRedirect(route('login'));
});

// --- Mass-assignment whitelist ---------------------------------------------

it('ignores authority fields submitted with a member edit', function () use ($payload) {
    $target = Member::factory()->create(['category' => Category::Active]);

    $this->actingAs(recordsOfficer())
        ->patch(route('members.update', $target), [
            ...$payload,
            'super_tier' => true,
            'category' => Category::Resigned->value,
        ])
        ->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->super_tier)->toBeFalse()
        ->and($target->category)->toBe(Category::Active);
});
