<?php

use App\Enums\ListingVisibility;
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
 * tour lists. Open-to-all Tours are marked. Non-members get the tab with a join notice and no Tour
 * data (ADR-0037 §2); super-tier sees it.
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
            ->where('joinNotice', false)
            ->where('can.manageTours', false)
            ->where('tours', [
                ['id' => $canada->id, 'name' => 'Canada', 'openToAll' => true, 'members' => []],
                ['id' => $birds->id, 'name' => 'Birds', 'openToAll' => false, 'members' => [
                    ['memberId' => $bea->member_id, 'name' => 'Bea Arthur', 'lastVetDate' => null],
                    ['memberId' => $ada->member_id, 'name' => 'Ada Lovelace', 'lastVetDate' => '2025-03-01'],
                ]],
            ]));
});

it('marks open-to-all Tours so an empty one reads as open to any Member, not "No one yet"', function () {
    $group = toursPageGroup();
    $viewer = toursPageMembershipOf($group)->member;
    $open = Tour::factory()->openToAll()->create(['group_id' => $group->id, 'name' => 'Canada', 'sort_order' => 0]);
    $openHeld = Tour::factory()->openToAll()->create(['group_id' => $group->id, 'name' => 'Dinos', 'sort_order' => 1]);
    $closed = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Egypt', 'sort_order' => 2]);
    $ada = toursPageMembershipOf($group, member: ['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Qualification::factory()->create(['group_member_id' => $ada->id, 'tour_id' => $openHeld->id, 'last_vet_date' => '2025-03-01']);

    $this->actingAs($viewer)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tours', [
            ['id' => $open->id, 'name' => 'Canada', 'openToAll' => true, 'members' => []],
            ['id' => $openHeld->id, 'name' => 'Dinos', 'openToAll' => true, 'members' => [
                ['memberId' => $ada->member_id, 'name' => 'Ada Lovelace', 'lastVetDate' => '2025-03-01'],
            ]],
            ['id' => $closed->id, 'name' => 'Egypt', 'openToAll' => false, 'members' => []],
        ]));

    expect(__('group.tours_page.open_to_all_none', [], 'en'))->toBe('Any member may give this tour.')
        ->and(__('group.tours_page.open_to_all_none', [], 'fr'))->toBe('Tout membre peut donner cette visite.')
        ->and(__('group.tours_page.none', [], 'en'))->toBe('No one yet.');
});

it('sends no Tours payload on other sections', function () {
    $group = toursPageGroup();
    Tour::factory()->create(['group_id' => $group->id]);

    $this->actingAs(toursPageMembershipOf($group)->member)
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page->where('tours', []));
});

it('shows a Member who is not in the Group the members-only tab with no Tour data', function () {
    $group = toursPageGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds']);
    Qualification::factory()->create(['group_member_id' => toursPageMembershipOf($group)->id, 'tour_id' => $tour->id]);
    $outsider = toursPageMembershipOf(toursPageGroup())->member;

    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'tours')
            ->where('group.capabilities.vetting', true)
            ->where('joinNotice', true)
            ->where('tours', []));
});

it('shows a Member whose Membership no longer counts the members-only tab', function (MembershipStatus $status) {
    $group = toursPageGroup();
    Tour::factory()->create(['group_id' => $group->id]);
    $former = toursPageMembershipOf($group, status: $status)->member;

    $this->actingAs($former)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('joinNotice', true)
            ->where('tours', []));
})->with([MembershipStatus::Resigned, MembershipStatus::Deceased, MembershipStatus::Inactive]);

it('404s a non-member of a Private Group on the Tours section', function () {
    $group = toursPageGroup(['listing_visibility' => ListingVisibility::Private]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertNotFound();
});

it('lets the super-tier see the page without a Membership', function () {
    $group = toursPageGroup();
    Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('joinNotice', false)
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
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.vetting', false));
});

it('tells a Vetting officer to link Tour rows to the by-Tour screens', function () {
    $group = toursPageGroup();
    $vetting = toursPageMembershipOf($group, Role::Vetting)->member;

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manageTours', true));
});

it('puts every Tour holder in the hand-pick pool, so a row\'s Email button can seed them (Feedback item 84)', function () {
    $group = toursPageGroup();
    $vetting = toursPageMembershipOf($group, Role::Vetting)->member;
    $birds = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds']);
    $holders = collect([MembershipStatus::Full, MembershipStatus::Trainee, MembershipStatus::Loa])
        ->map(fn (MembershipStatus $status) => toursPageMembershipOf($group, status: $status));
    $holders->each(fn (GroupMember $holder) => Qualification::factory()->create(['group_member_id' => $holder->id, 'tour_id' => $birds->id]));

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tours.0.members', fn ($members) => collect($members)->pluck('memberId')->sort()->values()->all() === $holders->pluck('member_id')->sort()->values()->all())
            ->where('email.roster', fn ($roster) => $holders->pluck('member_id')->diff(collect($roster)->pluck('id'))->isEmpty()));
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
