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
 * Keeping qualifications (#789, ADR-0033 §3, §4). A Vetting officer, the Chair or super-tier
 * records who may give each Tour on two screens, by Tour and by Member: add (reactivating an
 * inactive row), change the Last vet date, and remove. Inactive qualifications show apart. The
 * Member picker offers current Members only. Nothing expires.
 */

/** A vetting Group (scheduling on). */
function qualGroup(): Group
{
    return Group::factory()->program()->create(['has_vetting' => true]);
}

/** A Membership in $group, with an optional role and standing. */
function qualMembershipOf(Group $group, ?Role $role = null, MembershipStatus $status = MembershipStatus::Full, array $member = []): GroupMember
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

/** A Member of $group carrying an optional role. */
function qualMemberOf(Group $group, ?Role $role = null): Member
{
    return qualMembershipOf($group, $role)->member;
}

it('shows the by-Tour screen with active and inactive qualifications apart', function () {
    $this->travelTo('2026-10-10 12:00');
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinosaurs']);
    $vetting = qualMemberOf($group, Role::Vetting);
    $ada = qualMembershipOf($group, member: ['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $bea = qualMembershipOf($group, member: ['first_name' => 'Bea', 'last_name' => 'Arthur']);
    Qualification::factory()->create(['group_member_id' => $ada->id, 'tour_id' => $tour->id, 'last_vet_date' => '2025-03-01']);
    Qualification::factory()->inactive()->create(['group_member_id' => $bea->id, 'tour_id' => $tour->id, 'last_vet_date' => '2020-01-15']);

    $this->actingAs($vetting)
        ->get(route('groups.tours.show', ['group' => $group, 'tour' => $tour]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'tours')
            ->where('qualifications.view', 'tour')
            ->where('qualifications.tour.name', 'Dinosaurs')
            ->has('qualifications.active', 1)
            ->where('qualifications.active.0.membershipId', $ada->id)
            ->where('qualifications.active.0.name', 'Ada Lovelace')
            ->where('qualifications.active.0.lastVetDate', '2025-03-01')
            ->has('qualifications.inactive', 1)
            ->where('qualifications.inactive.0.membershipId', $bea->id)
            ->where('qualifications.inactive.0.lastVetDate', '2020-01-15')
            ->where('qualifications.today', '2026-10-10'));
});

it('offers only current Members not already active on the Tour in the by-Tour picker', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMembershipOf($group, Role::Vetting);
    $held = qualMembershipOf($group);
    $lapsed = qualMembershipOf($group);
    $onLoa = qualMembershipOf($group, status: MembershipStatus::Loa);
    qualMembershipOf($group, status: MembershipStatus::Resigned);
    qualMembershipOf($group, status: MembershipStatus::Deceased);
    qualMembershipOf($group, status: MembershipStatus::Inactive);
    qualMembershipOf(qualGroup());
    Qualification::factory()->create(['group_member_id' => $held->id, 'tour_id' => $tour->id]);
    Qualification::factory()->inactive()->create(['group_member_id' => $lapsed->id, 'tour_id' => $tour->id]);

    $this->actingAs($vetting->member)
        ->get(route('groups.tours.show', ['group' => $group, 'tour' => $tour]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('qualifications.candidates', fn ($candidates) => collect($candidates)->pluck('membershipId')->sort()->values()->all()
                === collect([$vetting->id, $lapsed->id, $onLoa->id])->sort()->values()->all()));
});

it('shows the by-Member screen with the Tours one Member gives', function () {
    $group = qualGroup();
    $first = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Birds', 'sort_order' => 0]);
    $second = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Canada', 'sort_order' => 1]);
    $third = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Egypt', 'sort_order' => 2]);
    Tour::factory()->inactive()->create(['group_id' => $group->id, 'name' => 'Retired']);
    $vetting = qualMemberOf($group, Role::Vetting);
    $docent = qualMembershipOf($group, member: ['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Qualification::factory()->create(['group_member_id' => $docent->id, 'tour_id' => $second->id, 'last_vet_date' => '2024-05-05']);
    Qualification::factory()->inactive()->create(['group_member_id' => $docent->id, 'tour_id' => $third->id]);

    $this->actingAs($vetting)
        ->get(route('groups.tours.member', ['group' => $group, 'membership' => $docent]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'tours')
            ->where('qualifications.view', 'member')
            ->where('qualifications.member.name', 'Ada Lovelace')
            ->where('qualifications.member.current', true)
            ->has('qualifications.active', 1)
            ->where('qualifications.active.0.tourId', $second->id)
            ->where('qualifications.active.0.name', 'Canada')
            ->where('qualifications.active.0.lastVetDate', '2024-05-05')
            ->has('qualifications.inactive', 1)
            ->where('qualifications.inactive.0.tourId', $third->id)
            // Active Tours not already held actively, in the Group's order.
            ->where('qualifications.candidates', [
                ['tourId' => $first->id, 'name' => 'Birds'],
                ['tourId' => $third->id, 'name' => 'Egypt'],
            ]));
});

it('serves both screens under /fr/', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $docent = qualMembershipOf($group);

    $this->withLocaleRoutes('fr', function () use ($group, $tour, $vetting, $docent) {
        $this->actingAs($vetting)->get("/fr/groupes/{$group->slug}/visites/{$tour->id}")->assertOk();
        $this->actingAs($vetting)->get("/fr/groupes/{$group->slug}/visites/membres/{$docent->id}")->assertOk();
    });
});

it('lets the Chair and super-tier open the screens and refuses everyone else', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $docent = qualMembershipOf($group);
    $tourUrl = route('groups.tours.show', ['group' => $group, 'tour' => $tour]);
    $memberUrl = route('groups.tours.member', ['group' => $group, 'membership' => $docent]);

    foreach ([qualMemberOf($group, Role::Chair), Member::factory()->superTier()->create()] as $allowed) {
        $this->actingAs($allowed)->get($tourUrl)->assertOk();
        $this->actingAs($allowed)->get($memberUrl)->assertOk();
    }

    foreach ([qualMemberOf($group, Role::Scheduler), qualMemberOf($group), $docent->member] as $refused) {
        $this->actingAs($refused)->get($tourUrl)->assertForbidden();
        $this->actingAs($refused)->get($memberUrl)->assertForbidden();
    }
});

it('404s a Tour or Membership of another Group, and a Group without vetting', function () {
    $group = qualGroup();
    $super = Member::factory()->superTier()->create();
    $otherTour = Tour::factory()->create();
    $otherMembership = qualMembershipOf(qualGroup());

    $this->actingAs($super)->get(route('groups.tours.show', ['group' => $group, 'tour' => $otherTour]))->assertNotFound();
    $this->actingAs($super)->get(route('groups.tours.member', ['group' => $group, 'membership' => $otherMembership]))->assertNotFound();

    $plain = Group::factory()->program()->create(['has_vetting' => false]);
    $plainTour = Tour::factory()->create(['group_id' => $plain->id]);
    $this->actingAs($super)->get(route('groups.tours.show', ['group' => $plain, 'tour' => $plainTour]))->assertNotFound();
});

it('lets a Vetting officer add a qualification with a Last vet date', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $docent = qualMembershipOf($group);

    $this->actingAs($vetting)
        ->post(route('groups.qualifications.store', ['group' => $group]), [
            'group_member_id' => $docent->id,
            'tour_id' => $tour->id,
            'last_vet_date' => '2026-10-10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $qualification = Qualification::query()->sole();
    expect($qualification->group_member_id)->toBe($docent->id);
    expect($qualification->tour_id)->toBe($tour->id);
    expect($qualification->active)->toBeTrue();
    expect($qualification->last_vet_date->toDateString())->toBe('2026-10-10');
});

it('reactivates an inactive qualification with the new date instead of adding a row', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $docent = qualMembershipOf($group);
    $existing = Qualification::factory()->inactive()->create([
        'group_member_id' => $docent->id, 'tour_id' => $tour->id, 'last_vet_date' => '2019-01-01',
    ]);

    $this->actingAs($vetting)
        ->post(route('groups.qualifications.store', ['group' => $group]), [
            'group_member_id' => $docent->id,
            'tour_id' => $tour->id,
            'last_vet_date' => '2026-10-01',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Qualification::query()->count())->toBe(1);
    $existing->refresh();
    expect($existing->active)->toBeTrue();
    expect($existing->last_vet_date->toDateString())->toBe('2026-10-01');
});

it('lets the Chair and super-tier add a qualification and refuses a Scheduler and a Member', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $docent = qualMembershipOf($group);
    $payload = ['group_member_id' => $docent->id, 'tour_id' => $tour->id, 'last_vet_date' => '2026-10-10'];

    foreach ([qualMemberOf($group, Role::Scheduler), qualMemberOf($group)] as $refused) {
        $this->actingAs($refused)->post(route('groups.qualifications.store', ['group' => $group]), $payload)->assertForbidden();
    }
    expect(Qualification::query()->count())->toBe(0);

    $this->actingAs(qualMemberOf($group, Role::Chair))
        ->post(route('groups.qualifications.store', ['group' => $group]), $payload)
        ->assertSessionHasNoErrors();
    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('groups.qualifications.store', ['group' => $group]), $payload)
        ->assertSessionHasNoErrors();
    expect(Qualification::query()->count())->toBe(1);
});

it('refuses to qualify a Member who is not current in the Group, or on another Group\'s Tour', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $resigned = qualMembershipOf($group, status: MembershipStatus::Resigned);
    $outsider = qualMembershipOf(qualGroup());
    $docent = qualMembershipOf($group);
    $foreignTour = Tour::factory()->create();

    $store = fn (array $data) => $this->actingAs($vetting)
        ->post(route('groups.qualifications.store', ['group' => $group]), [...$data, 'last_vet_date' => '2026-10-10']);

    $store(['group_member_id' => $resigned->id, 'tour_id' => $tour->id])->assertSessionHasErrors('group_member_id');
    $store(['group_member_id' => $outsider->id, 'tour_id' => $tour->id])->assertSessionHasErrors('group_member_id');
    $store(['group_member_id' => $docent->id, 'tour_id' => $foreignTour->id])->assertSessionHasErrors('tour_id');
    $this->actingAs($vetting)
        ->post(route('groups.qualifications.store', ['group' => $group]), ['group_member_id' => $docent->id, 'tour_id' => $tour->id])
        ->assertSessionHasErrors('last_vet_date');

    expect(Qualification::query()->count())->toBe(0);
});

it('lets a Vetting officer change a Last vet date', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $qualification = Qualification::factory()->create([
        'group_member_id' => qualMembershipOf($group)->id, 'tour_id' => $tour->id, 'last_vet_date' => '2022-02-02',
    ]);

    $this->actingAs($vetting)
        ->patch(route('qualifications.update', ['qualification' => $qualification]), ['last_vet_date' => '2026-09-30'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($qualification->refresh()->last_vet_date->toDateString())->toBe('2026-09-30');
    expect($qualification->active)->toBeTrue();
});

it('refuses a Last vet date after today on the org clock when adding a qualification', function () {
    // 02:00 UTC on the 11th is still the evening of the 10th at the museum.
    $this->travelTo('2026-10-11 02:00:00');
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $docent = qualMembershipOf($group);

    $store = fn (string $date) => $this->actingAs($vetting)
        ->post(route('groups.qualifications.store', ['group' => $group]), [
            'group_member_id' => $docent->id, 'tour_id' => $tour->id, 'last_vet_date' => $date,
        ]);

    $store('2026-10-11')->assertSessionHasErrors(['last_vet_date' => __('group.qualifications.future_date')]);
    expect(Qualification::query()->count())->toBe(0);

    $store('2026-10-10')->assertSessionHasNoErrors();
    expect(Qualification::query()->sole()->last_vet_date->toDateString())->toBe('2026-10-10');

    $store('2024-01-05')->assertSessionHasNoErrors();
    expect(Qualification::query()->sole()->last_vet_date->toDateString())->toBe('2024-01-05');
});

it('refuses a Last vet date after today on the org clock when changing the date', function () {
    $this->travelTo('2026-10-11 02:00:00');
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $qualification = Qualification::factory()->create([
        'group_member_id' => qualMembershipOf($group)->id, 'tour_id' => $tour->id, 'last_vet_date' => '2022-02-02',
    ]);

    $update = fn (string $date) => $this->actingAs($vetting)
        ->patch(route('qualifications.update', ['qualification' => $qualification]), ['last_vet_date' => $date]);

    $update('2026-10-11')->assertSessionHasErrors(['last_vet_date' => __('group.qualifications.future_date')]);
    expect($qualification->refresh()->last_vet_date->toDateString())->toBe('2022-02-02');

    $update('2026-10-10')->assertSessionHasNoErrors();
    expect($qualification->refresh()->last_vet_date->toDateString())->toBe('2026-10-10');
});

it('lets a Vetting officer remove a qualification', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $qualification = Qualification::factory()->create(['group_member_id' => qualMembershipOf($group)->id, 'tour_id' => $tour->id]);

    $this->actingAs($vetting)
        ->delete(route('qualifications.destroy', ['qualification' => $qualification]))
        ->assertRedirect();

    expect(Qualification::query()->count())->toBe(0);
});

it('refuses a Scheduler or a Member changing or removing a qualification', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $holder = qualMembershipOf($group);
    $qualification = Qualification::factory()->create([
        'group_member_id' => $holder->id, 'tour_id' => $tour->id, 'last_vet_date' => '2022-02-02',
    ]);

    foreach ([qualMemberOf($group, Role::Scheduler), $holder->member] as $refused) {
        $this->actingAs($refused)
            ->patch(route('qualifications.update', ['qualification' => $qualification]), ['last_vet_date' => '2026-09-30'])
            ->assertForbidden();
        $this->actingAs($refused)
            ->delete(route('qualifications.destroy', ['qualification' => $qualification]))
            ->assertForbidden();
    }

    expect($qualification->refresh()->last_vet_date->toDateString())->toBe('2022-02-02');
});

it('refuses a Vetting officer of another Group', function () {
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $qualification = Qualification::factory()->create(['group_member_id' => qualMembershipOf($group)->id, 'tour_id' => $tour->id]);
    $stranger = qualMemberOf(qualGroup(), Role::Vetting);

    $this->actingAs($stranger)
        ->delete(route('qualifications.destroy', ['qualification' => $qualification]))
        ->assertForbidden();
    $this->actingAs($stranger)
        ->post(route('groups.qualifications.store', ['group' => $group]), [
            'group_member_id' => $qualification->group_member_id, 'tour_id' => $tour->id, 'last_vet_date' => '2026-10-10',
        ])
        ->assertForbidden();
});

it('shows an old Last vet date as a date, with nothing expiring', function () {
    $this->travelTo('2026-10-10 12:00');
    $group = qualGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = qualMemberOf($group, Role::Vetting);
    $veteran = qualMembershipOf($group);
    Qualification::factory()->create(['group_member_id' => $veteran->id, 'tour_id' => $tour->id, 'last_vet_date' => '2009-06-01']);

    $this->actingAs($vetting)
        ->get(route('groups.tours.show', ['group' => $group, 'tour' => $tour]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('qualifications.active', 1)
            ->where('qualifications.active.0.lastVetDate', '2009-06-01'));
});

it('links Tour names on the Settings card and roster rows for a Vetting officer', function () {
    $group = qualGroup();
    $vetting = qualMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageTours', true));

    $this->actingAs(qualMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageTours', false));
});
