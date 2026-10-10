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
 * The status rules and the Tour rules card (#793, ADR-0033 §7). When a Membership's standing
 * changes, its qualifications follow the Group's settings: the trainee Tour, the starter Tours and
 * the LOA rule. Rules activate or deactivate rows, never delete them. Removing a Membership deletes
 * its qualifications. The Chair and super-tier set the rules in Group Settings.
 */

/** A vetting Group with a trainee Tour, one starter Tour and one other Tour. */
function rulesGroup(bool $loaRemoves = false): Group
{
    $group = Group::factory()->program()->create(['has_vetting' => true, 'loa_removes_qualifications' => $loaRemoves]);
    $trainee = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Highlights – New Docents']);
    Tour::factory()->starter()->create(['group_id' => $group->id, 'name' => 'Highlights']);
    Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinosaurs']);
    $group->update(['trainee_tour_id' => $trainee->id]);

    return $group;
}

function rulesTour(Group $group, string $name): Tour
{
    return $group->tours()->where('name', $name)->sole();
}

/** A Membership in $group with an optional role and standing. */
function rulesMembershipOf(Group $group, ?Role $role = null, MembershipStatus $status = MembershipStatus::Full): GroupMember
{
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create()->id,
        'status' => $status,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $membership;
}

function rulesQualify(GroupMember $membership, Tour $tour, bool $active = true, string $date = '2024-05-01'): Qualification
{
    return Qualification::factory()->create([
        'group_member_id' => $membership->id,
        'tour_id' => $tour->id,
        'active' => $active,
        'last_vet_date' => $date,
    ]);
}

/** Tour name => [active, last vet date] for a Membership. */
function rulesState(GroupMember $membership): array
{
    return Qualification::where('group_member_id', $membership->id)->with('tour')->get()
        ->mapWithKeys(fn (Qualification $q) => [$q->tour->name => [$q->active, $q->last_vet_date?->toDateString()]])
        ->sortKeys()->all();
}

function rulesSetStatus(GroupMember $membership, MembershipStatus $status)
{
    $secretary = rulesMembershipOf($membership->group, Role::Secretary)->member;

    return test()->actingAs($secretary)
        ->patch(route('group-members.update', $membership), ['status' => $status->value])
        ->assertSessionHasNoErrors();
}

beforeEach(fn () => $this->travelTo('2026-10-10 12:00'));

// --- Status rules -------------------------------------------------------------

it('makes every qualification inactive and the trainee Tour active on becoming Trainee', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group, status: MembershipStatus::Full);
    rulesQualify($membership, rulesTour($group, 'Highlights'));
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, MembershipStatus::Trainee);

    expect(rulesState($membership))->toBe([
        'Dinosaurs' => [false, '2024-05-01'],
        'Highlights' => [false, '2024-05-01'],
        'Highlights – New Docents' => [true, '2026-10-10'],
    ]);
});

it('activates the starter Tours and deactivates the trainee Tour on becoming Full', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group, status: MembershipStatus::Trainee);
    rulesQualify($membership, rulesTour($group, 'Highlights – New Docents'));
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'), active: false);

    rulesSetStatus($membership, MembershipStatus::Full);

    expect(rulesState($membership))->toBe([
        'Dinosaurs' => [false, '2024-05-01'],
        'Highlights' => [true, '2026-10-10'],
        'Highlights – New Docents' => [false, '2024-05-01'],
    ]);
});

it('leaves qualifications on a retired starter Tour inactive on becoming Full', function () {
    $group = rulesGroup();
    rulesTour($group, 'Highlights')->update(['active' => false]);
    $membership = rulesMembershipOf($group, status: MembershipStatus::Emeritus);
    rulesQualify($membership, rulesTour($group, 'Highlights'), active: false, date: '2019-01-01');
    $fresh = rulesMembershipOf($group, status: MembershipStatus::Trainee);

    rulesSetStatus($membership, MembershipStatus::Full);
    rulesSetStatus($fresh, MembershipStatus::Full);

    expect(rulesState($membership))->toBe(['Highlights' => [false, '2019-01-01']])
        ->and(rulesState($fresh))->toBe([]);
});

it('leaves a qualification on a retired trainee Tour inactive on becoming Trainee', function () {
    $group = rulesGroup();
    rulesTour($group, 'Highlights – New Docents')->update(['active' => false]);
    $membership = rulesMembershipOf($group, status: MembershipStatus::Emeritus);
    rulesQualify($membership, rulesTour($group, 'Highlights – New Docents'), active: false);

    rulesSetStatus($membership, MembershipStatus::Trainee);

    expect(rulesState($membership))->toBe(['Highlights – New Docents' => [false, '2024-05-01']]);
});

it('reactivates an inactive starter qualification with today as its Last vet date', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group, status: MembershipStatus::Emeritus);
    $row = rulesQualify($membership, rulesTour($group, 'Highlights'), active: false, date: '2019-01-01');

    rulesSetStatus($membership, MembershipStatus::Full);

    expect($row->fresh()->active)->toBeTrue()
        ->and($row->fresh()->last_vet_date->toDateString())->toBe('2026-10-10')
        ->and(Qualification::count())->toBe(1);
});

it('leaves an already active starter qualification and its date alone', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group, status: MembershipStatus::Transitional);
    $row = rulesQualify($membership, rulesTour($group, 'Highlights'), date: '2023-02-02');

    rulesSetStatus($membership, MembershipStatus::Full);

    expect($row->fresh()->last_vet_date->toDateString())->toBe('2023-02-02');
});

it('makes every qualification inactive on becoming Emeritus, Resigned or Deceased', function (MembershipStatus $status) {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group);
    rulesQualify($membership, rulesTour($group, 'Highlights'));
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, $status);

    expect(rulesState($membership))->toBe([
        'Dinosaurs' => [false, '2024-05-01'],
        'Highlights' => [false, '2024-05-01'],
    ]);
})->with([MembershipStatus::Emeritus, MembershipStatus::Resigned, MembershipStatus::Deceased]);

it('makes every qualification inactive on becoming LOA only when the LOA rule is on', function (bool $loaRemoves) {
    $group = rulesGroup(loaRemoves: $loaRemoves);
    $membership = rulesMembershipOf($group);
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, MembershipStatus::Loa);

    expect(rulesState($membership))->toBe(['Dinosaurs' => [! $loaRemoves, '2024-05-01']]);
})->with([true, false]);

it('changes nothing on a standing the rules do not name', function (MembershipStatus $status) {
    $group = rulesGroup(loaRemoves: true);
    $membership = rulesMembershipOf($group);
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, $status);

    expect(rulesState($membership))->toBe(['Dinosaurs' => [true, '2024-05-01']]);
})->with([MembershipStatus::Inactive, MembershipStatus::Transitional, MembershipStatus::Auxiliary, MembershipStatus::Projects]);

it('changes nothing when the standing stays the same', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group, status: MembershipStatus::Trainee);
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, MembershipStatus::Trainee);

    expect(rulesState($membership))->toBe(['Dinosaurs' => [true, '2024-05-01']]);
});

it('gives a Member added as a Trainee the trainee Tour, and one added as Full the starter Tours', function () {
    $group = rulesGroup();
    $secretary = rulesMembershipOf($group, Role::Secretary)->member;
    $trainee = Member::factory()->create();
    $full = Member::factory()->create();

    $this->actingAs($secretary)
        ->post(route('group-members.store', $group), ['member_id' => $trainee->id, 'status' => 'trainee'])
        ->assertSessionHasNoErrors();
    $this->actingAs($secretary)
        ->post(route('group-members.store', $group), ['member_id' => $full->id])
        ->assertSessionHasNoErrors();

    expect(rulesState($trainee->membershipIn($group)))->toBe(['Highlights – New Docents' => [true, '2026-10-10']])
        ->and(rulesState($full->fresh()->membershipIn($group)))->toBe(['Highlights' => [true, '2026-10-10']]);
});

it('runs no rules in a Group without vetting', function () {
    $group = Group::factory()->program()->create(['has_vetting' => false]);
    $tour = Tour::factory()->starter()->create(['group_id' => $group->id]);
    $membership = rulesMembershipOf($group, status: MembershipStatus::Trainee);
    rulesQualify($membership, $tour, active: false);

    rulesSetStatus($membership, MembershipStatus::Full);

    expect(Qualification::sole()->active)->toBeFalse();
});

it('works with no trainee Tour set', function () {
    $group = rulesGroup();
    $group->update(['trainee_tour_id' => null]);
    $membership = rulesMembershipOf($group);
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));

    rulesSetStatus($membership, MembershipStatus::Trainee);

    expect(rulesState($membership))->toBe(['Dinosaurs' => [false, '2024-05-01']]);
});

it('deletes the qualifications when a Membership is removed, and they never block it', function () {
    $group = rulesGroup();
    $membership = rulesMembershipOf($group);
    rulesQualify($membership, rulesTour($group, 'Dinosaurs'));
    rulesQualify($membership, rulesTour($group, 'Highlights'), active: false);
    $other = rulesMembershipOf($group);
    rulesQualify($other, rulesTour($group, 'Dinosaurs'));
    $secretary = rulesMembershipOf($group, Role::Secretary)->member;

    $this->actingAs($secretary)
        ->delete(route('group-members.destroy', $membership))
        ->assertSessionHasNoErrors();

    expect(GroupMember::find($membership->id))->toBeNull()
        ->and(Qualification::where('group_member_id', $membership->id)->exists())->toBeFalse()
        ->and(Qualification::where('group_member_id', $other->id)->count())->toBe(1);
});

// --- The Tour rules card ------------------------------------------------------

it('shows the Tour rules card to the Chair of a vetting Group', function () {
    $group = rulesGroup(loaRemoves: true);
    $chair = rulesMembershipOf($group, Role::Chair)->member;

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTourRules', true)
            ->where('settings.tourRules.traineeTourId', rulesTour($group, 'Highlights – New Docents')->id)
            ->where('settings.tourRules.starterTourIds', [rulesTour($group, 'Highlights')->id])
            ->where('settings.tourRules.loaRemovesQualifications', true)
            ->has('settings.tourRules.tours', 3));
});

it('hides the Tour rules card from a Vetting officer, and from everyone without vetting', function () {
    $group = rulesGroup();
    $vetting = rulesMembershipOf($group, Role::Vetting)->member;

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTourRules', false)
            ->where('settings.tourRules', null));

    $plain = Group::factory()->program()->create();
    $chair = rulesMembershipOf($plain, Role::Chair)->member;

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $plain, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTourRules', false)
            ->where('settings.tourRules', null));
});

it('lets the Chair and super-tier save the Tour rules', function (string $who) {
    $group = rulesGroup();
    $actor = $who === 'chair'
        ? rulesMembershipOf($group, Role::Chair)->member
        : Member::factory()->superTier()->create();
    $dinosaurs = rulesTour($group, 'Dinosaurs');
    $highlights = rulesTour($group, 'Highlights');

    $this->actingAs($actor)
        ->patch(route('groups.tour-rules.update', $group), [
            'trainee_tour_id' => null,
            'starter_tour_ids' => [$dinosaurs->id, $highlights->id],
            'loa_removes_qualifications' => true,
        ])
        ->assertSessionHasNoErrors();

    $group->refresh();
    expect($group->trainee_tour_id)->toBeNull()
        ->and($group->loa_removes_qualifications)->toBeTrue()
        ->and($group->tours()->where('starter', true)->pluck('name')->sort()->values()->all())->toBe(['Dinosaurs', 'Highlights']);
})->with(['chair', 'super-tier']);

it('refuses the Tour rules to a Vetting officer, a Secretary and a plain Member', function (?Role $role) {
    $group = rulesGroup();
    $actor = rulesMembershipOf($group, $role)->member;

    $this->actingAs($actor)
        ->patch(route('groups.tour-rules.update', $group), [
            'trainee_tour_id' => null,
            'starter_tour_ids' => [],
            'loa_removes_qualifications' => true,
        ])
        ->assertForbidden();

    expect($group->fresh()->loa_removes_qualifications)->toBeFalse();
})->with([Role::Vetting, Role::Secretary, null]);

it('refuses the Tour rules on a Group without vetting', function () {
    $group = Group::factory()->program()->create();
    $chair = rulesMembershipOf($group, Role::Chair)->member;

    $this->actingAs($chair)
        ->patch(route('groups.tour-rules.update', $group), [
            'trainee_tour_id' => null,
            'starter_tour_ids' => [],
            'loa_removes_qualifications' => true,
        ])
        ->assertForbidden();
});

it('refuses another Group\'s Tour and a trainee Tour that is also a starter', function () {
    $group = rulesGroup();
    $chair = rulesMembershipOf($group, Role::Chair)->member;
    $foreign = Tour::factory()->create();
    $trainee = rulesTour($group, 'Highlights – New Docents');

    $this->actingAs($chair)
        ->patch(route('groups.tour-rules.update', $group), [
            'trainee_tour_id' => $foreign->id,
            'starter_tour_ids' => [$foreign->id],
            'loa_removes_qualifications' => false,
        ])
        ->assertSessionHasErrors(['trainee_tour_id', 'starter_tour_ids.0']);

    $this->actingAs($chair)
        ->patch(route('groups.tour-rules.update', $group), [
            'trainee_tour_id' => $trainee->id,
            'starter_tour_ids' => [$trainee->id],
            'loa_removes_qualifications' => false,
        ])
        ->assertSessionHasErrors('starter_tour_ids');
});

it('clears the trainee Tour setting when that Tour is deleted', function () {
    $group = rulesGroup();
    $vetting = rulesMembershipOf($group, Role::Vetting)->member;

    $this->actingAs($vetting)
        ->delete(route('tours.destroy', rulesTour($group, 'Highlights – New Docents')))
        ->assertSessionHasNoErrors();

    expect($group->fresh()->trainee_tour_id)->toBeNull();
});
