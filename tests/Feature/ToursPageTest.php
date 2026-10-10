<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\Tour;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Tours page (#792, ADR-0033 §5). Members of a vetting Group see each active Tour and the
 * Members who hold an active qualification for it, with their Last vet dates — the old Who's Who
 * tour lists. Open-to-all Tours are marked. Non-members get 403 and no tab; super-tier sees it.
 */

/** A vetting Group. */
function toursPageGroup(array $attributes = []): Group
{
    return Group::factory()->program()->create(['has_vetting' => true, ...$attributes]);
}

/** A Membership in $group, with an optional role and Member attributes. */
function toursPageMembershipOf(Group $group, ?Role $role = null, array $member = [], MembershipStatus $status = MembershipStatus::Full): GroupMember
{
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create($member)->id,
        'status' => $status,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $membership;
}

it('shows a Member each active Tour with its active holders and Last vet dates', function () {
    $group = toursPageGroup();
    $viewer = toursPageMembershipOf($group)->member;
    $birds = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds', 'sort_order' => 1]);
    $canada = Tour::factory()->openToAll()->create(['group_id' => $group->id, 'name' => 'Canada', 'sort_order' => 0]);
    $retired = Tour::factory()->inactive()->create(['group_id' => $group->id, 'name' => 'Retired', 'sort_order' => 2]);
    $ada = toursPageMembershipOf($group, member: ['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $bea = toursPageMembershipOf($group, member: ['first_name' => 'Bea', 'last_name' => 'Arthur']);
    $cal = toursPageMembershipOf($group, member: ['first_name' => 'Cal', 'last_name' => 'Zed']);
    Qualification::factory()->create(['group_member_id' => $ada->id, 'tour_id' => $birds->id, 'last_vet_date' => '2025-03-01']);
    Qualification::factory()->create(['group_member_id' => $bea->id, 'tour_id' => $birds->id, 'last_vet_date' => null]);
    Qualification::factory()->inactive()->create(['group_member_id' => $cal->id, 'tour_id' => $birds->id]);
    Qualification::factory()->create(['group_member_id' => $cal->id, 'tour_id' => $retired->id]);

    $this->actingAs($viewer)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'tours')
            ->where('qualifications', null)
            ->where('group.capabilities.vetting', true)
            ->where('can.viewTours', true)
            ->where('can.manageTours', false)
            ->where('tours', [
                ['id' => $canada->id, 'name' => 'Canada', 'openToAll' => true, 'members' => []],
                ['id' => $birds->id, 'name' => 'Birds', 'openToAll' => false, 'members' => [
                    ['memberId' => $bea->member_id, 'name' => 'Bea Arthur', 'lastVetDate' => null],
                    ['memberId' => $ada->member_id, 'name' => 'Ada Lovelace', 'lastVetDate' => '2025-03-01'],
                ]],
            ]));
});

it('sends no Tours payload on other sections', function () {
    $group = toursPageGroup();
    Tour::factory()->create(['group_id' => $group->id]);

    $this->actingAs(toursPageMembershipOf($group)->member)
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page->where('tours', []));
});

it('forbids a Member who is not in the Group and hides the tab hint', function () {
    $group = toursPageGroup();
    $outsider = toursPageMembershipOf(toursPageGroup())->member;

    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page->where('can.viewTours', false));
});

it('lets the super-tier see the page without a Membership', function () {
    $group = toursPageGroup();
    Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewTours', true)
            ->where('tours.0.name', 'Birds'));
});

it('404s when the Group runs no vetting, super-tier included', function () {
    $group = toursPageGroup(['has_vetting' => false]);

    $this->actingAs(toursPageMembershipOf($group)->member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertNotFound();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertNotFound();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page->where('can.viewTours', false));
});

it('tells a Vetting officer to link Tour names to the by-Tour screens', function () {
    $group = toursPageGroup();
    $vetting = toursPageMembershipOf($group, Role::Vetting)->member;

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manageTours', true));
});

it('serves the page under /fr/visites', function () {
    $group = toursPageGroup();
    $viewer = toursPageMembershipOf($group)->member;

    $this->withLocaleRoutes('fr', function () use ($group, $viewer) {
        $this->actingAs($viewer)
            ->get("/fr/groupes/{$group->slug}/visites")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('section', 'tours'));
    });
});
