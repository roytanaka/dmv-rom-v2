<?php

use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Parent officers manage a sub-group's roster (#882, PRD #881, ADR-0036 §11). A Group's
 * Secretary or Chair manages the roster of every Group below it, to any depth, exactly as
 * the sub-group's own officers do. Authority runs down the tree only, never sideways or up,
 * and confers no content: the About text, banner, Meetings and Documents stay with the
 * sub-group's own officers.
 */

/** A member of the given Group holding the given role. */
function parentOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** A sub-group directly below the given parent. */
function subGroupOf(Group $parent, array $attributes = []): Group
{
    return Group::factory()->create(['parent_id' => $parent->id, ...$attributes]);
}

it('lets a parent Chair and a parent Secretary add a member to a sub-group', function (Role $role) {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $target = Member::factory()->create();

    $this->actingAs(parentOfficerOf($parent, $role))
        ->post(route('group-members.store', $sub), ['member_ids' => [$target->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(GroupMember::where('group_id', $sub->id)->where('member_id', $target->id)->exists())->toBeTrue();
})->with([Role::Chair, Role::Secretary]);

it('shows a parent officer the Add member controls on a sub-group roster', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $outsider = Member::factory()->create();

    $this->actingAs(parentOfficerOf($parent, Role::Chair))
        ->get(route('groups.show', ['group' => $sub, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageRoster', true)
            ->where('rosterMeta.candidates', fn (Collection $candidates) => $candidates->contains('id', $outsider->id)));
});

it('lets a parent officer change a sub-group member standing and leave window', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);

    $this->actingAs(parentOfficerOf($parent, Role::Secretary))
        ->patch(route('group-members.update', $membership), [
            'status' => MembershipStatus::Loa->value,
            'loa_start' => '2026-11-01',
            'loa_end' => '2027-02-01',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $fresh = $membership->fresh();
    expect($fresh->status)->toBe(MembershipStatus::Loa)
        ->and($fresh->loa_start->toDateString())->toBe('2026-11-01')
        ->and($fresh->loa_end->toDateString())->toBe('2027-02-01');
});

it('lets a parent officer assign and revoke a sub-group Chair', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);
    $chair = parentOfficerOf($parent, Role::Chair);

    $this->actingAs($chair)
        ->patch(route('group-members.update', $membership), ['roles' => [Role::Chair->value]])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles->pluck('role')->all())->toBe([Role::Chair]);

    $this->actingAs($chair)
        ->patch(route('group-members.update', $membership), ['roles' => []])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->roles)->toBeEmpty();
});

it('lets a parent officer resign a sub-group member', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);

    $this->actingAs(parentOfficerOf($parent, Role::Chair))
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Resigned->value])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Resigned);
});

it('lets a parent officer hard-remove a sub-group member added in error', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);

    $this->actingAs(parentOfficerOf($parent, Role::Secretary))
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($membership->fresh())->toBeNull();
});

it('gives a grandparent officer the same roster powers over a grandchild', function () {
    $grandparent = Group::factory()->create();
    $grandchild = subGroupOf(subGroupOf($grandparent));
    $chair = parentOfficerOf($grandparent, Role::Chair);
    $target = Member::factory()->create();

    $this->actingAs($chair)
        ->post(route('group-members.store', $grandchild), ['member_ids' => [$target->id]])
        ->assertSessionHasNoErrors();

    $membership = GroupMember::where('group_id', $grandchild->id)->where('member_id', $target->id)->sole();

    $this->actingAs($chair)
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Inactive);

    $this->actingAs($chair)
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasNoErrors();

    expect($membership->fresh())->toBeNull();
});

it('refuses an officer of an unrelated Group', function () {
    $sub = subGroupOf(Group::factory()->create());
    $outsider = parentOfficerOf(Group::factory()->create(), Role::Chair);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);

    $this->actingAs($outsider)
        ->post(route('group-members.store', $sub), ['member_ids' => [Member::factory()->create()->id]])
        ->assertForbidden();
    $this->actingAs($outsider)
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertForbidden();
    $this->actingAs($outsider)
        ->delete(route('group-members.destroy', $membership))
        ->assertForbidden();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Full);
});

it('refuses a sub-group officer the roster of the Group above', function () {
    $parent = Group::factory()->create();
    $subChair = parentOfficerOf(subGroupOf($parent), Role::Chair);

    $this->actingAs($subChair)
        ->post(route('group-members.store', $parent), ['member_ids' => [Member::factory()->create()->id]])
        ->assertForbidden();
});

it('confers nothing from a role on the root DMV Group', function () {
    $root = Group::factory()->create(['slug' => Group::ROOT_SLUG]);
    $sub = subGroupOf($root);
    $rootChair = parentOfficerOf($root, Role::Chair);

    $this->actingAs($rootChair)
        ->post(route('group-members.store', $sub), ['member_ids' => [Member::factory()->create()->id]])
        ->assertForbidden();
    $this->actingAs($rootChair)
        ->post(route('group-members.store', $root), ['member_ids' => [Member::factory()->create()->id]])
        ->assertForbidden();
});

it('gives a parent officer no content rights in a sub-group they have not joined', function () {
    Storage::fake('local');
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent, ['has_meetings' => true, 'has_documents' => true, 'description' => 'Ours.']);
    $chair = parentOfficerOf($parent, Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.update', $sub), ['description' => 'Rewritten.'])
        ->assertForbidden();
    $this->actingAs($chair)
        ->patch(route('groups.update', $sub), ['banner_key' => null])
        ->assertForbidden();
    $this->actingAs($chair)
        ->post(route('meetings.store', $sub), ['title' => 'Planning', 'held_at' => '2026-11-12 14:00:00'])
        ->assertForbidden();
    $this->actingAs($chair)
        ->post(route('documents.store', $sub), ['file' => UploadedFile::fake()->create('minutes.pdf', 5, 'application/pdf')])
        ->assertForbidden();

    expect($sub->fresh()->description)->toBe('Ours.')
        ->and($sub->meetings()->exists())->toBeFalse()
        ->and($sub->documents()->exists())->toBeFalse();
});

it('gives an officer of an archived Group no roster authority over its sub-groups', function () {
    $parent = Group::factory()->archived()->create();
    $sub = subGroupOf($parent);
    $membership = GroupMember::factory()->create(['group_id' => $sub->id]);
    $chair = parentOfficerOf($parent, Role::Chair);

    $this->actingAs($chair)
        ->post(route('group-members.store', $sub), ['member_ids' => [Member::factory()->create()->id]])
        ->assertForbidden();
    $this->actingAs($chair)
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertForbidden();
});

it('lets a parent officer reach a Private sub-group roster and add themselves, its content still closed', function () {
    $parent = Group::factory()->create();
    $sub = subGroupOf($parent, [
        'listing_visibility' => ListingVisibility::Private,
        'has_meetings' => true,
        'has_documents' => true,
        'description' => 'Private about text.',
        'how_to_join' => 'Private join steps.',
    ]);
    $chair = parentOfficerOf($parent, Role::Chair);

    // The roster opens with the Add member controls; the About text stays out of the payload.
    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $sub, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageRoster', true)
            ->where('can.update', false)
            ->where('overview.description', null)
            ->where('overview.how_to_join', null)
            ->where('rosterMeta.candidates', fn (Collection $candidates) => $candidates->contains('id', $chair->id)));

    // Every other section stays hidden, as for any non-member.
    foreach (['overview', 'meetings', 'documents'] as $section) {
        $this->actingAs($chair)
            ->get(route('groups.show', ['group' => $sub, 'section' => $section]))
            ->assertNotFound();
    }

    $this->actingAs($chair)
        ->post(route('group-members.store', $sub), ['member_ids' => [$chair->id]])
        ->assertSessionHasNoErrors();

    expect(GroupMember::where('group_id', $sub->id)->where('member_id', $chair->id)->exists())->toBeTrue();

    // Joined, the Group opens to them as to any member.
    $chair->unsetRelation('memberships');
    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $sub, 'section' => 'overview']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('overview.description', 'Private about text.'));
});
