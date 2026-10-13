<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\ShiftAudience;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A self sign-up picks and checks a Tour (#790, ADR-0033 §2, §6). On a shift kind that maps to
 * Tours, a Member's own Sign-up must carry one of the kind's active Tours that they hold an active
 * qualification for, or that is open to all. A one-Tour kind fills it in; a multi-Tour kind asks.
 * The Sign-up records the Tour and it shows wherever the Sign-up does.
 */

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-06-15 14:00:00', config('app.org_timezone'))));

/** A Docents-like Group: scheduling, vetting, a visitor count, listed org-wide. */
function signUpTourGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create([
        'has_scheduling' => true,
        'has_vetting' => true,
    ]);
}

/** A Full Member of the Group, returned with the Membership. */
function signUpTourMember(Group $group): Member
{
    $member = Member::factory()->category(Category::Active)->create();
    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    return $member;
}

/** A kind mapped to the given Tours. */
function signUpTourKind(Group $group, array $tours, string $name = 'Gallery/Theme'): ShiftKind
{
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => $name]);
    $kind->tours()->attach(collect($tours)->pluck('id'));

    return $kind;
}

/** An active Tour of the Group. */
function signUpTourTour(Group $group, string $name, array $overrides = []): Tour
{
    return Tour::factory()->create(['group_id' => $group->id, 'name' => $name, ...$overrides]);
}

/** Give the Member a qualification on the Tour. */
function signUpTourQualify(Member $member, Tour $tour, bool $active = true): void
{
    Qualification::factory()->create([
        'group_member_id' => $member->membershipIn($tour->group)->id,
        'tour_id' => $tour->id,
        'active' => $active,
    ]);
}

/** A published June schedule with one open-seat Shift tomorrow on the kind. */
function signUpTourShift(Group $group, ?ShiftKind $kind, ShiftAudience $audience = ShiftAudience::Group): Shift
{
    $schedule = Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
    ]);

    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind?->id,
        'capacity' => 2,
        'audience' => $audience,
        'starts_at' => Carbon::parse('2026-06-16 10:00', config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse('2026-06-16 11:00', config('app.org_timezone'))->utc(),
    ]);
}

function signUpTourPage(Shift $shift): string
{
    return route('groups.scheduling.show', ['group' => $shift->schedule->group, 'schedule' => $shift->schedule_id]);
}

// --- the write: which Tour a self sign-up may carry -----------------------------

it('records the chosen Tour on a multi-Tour kind when the Member is qualified', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $dinos = signUpTourTour($group, 'Dinosaurs');
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt, $dinos]));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $dinos);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $dinos->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($dinos->id);
});

it('refuses a sign-up with no Tour when the Member may give several of the kind\'s Tours', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $dinos = signUpTourTour($group, 'Dinosaurs');
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt, $dinos]));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $egypt);
    signUpTourQualify($member, $dinos);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasErrors(['tour_id' => 'Choose the tour you will give.']);

    expect(SignUp::count())->toBe(0);
});

it('refuses a Tour the Member is not qualified for, holds only inactively, or that the kind does not map', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $dinos = signUpTourTour($group, 'Dinosaurs');
    $birds = signUpTourTour($group, 'Birds');
    $other = signUpTourTour($group, 'Unmapped', ['open_to_all' => true]);
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt, $dinos, $birds]));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $dinos);
    signUpTourQualify($member, $birds, active: false);

    foreach ([$egypt, $birds, $other] as $refused) {
        $this->actingAs($member)
            ->post(route('sign-ups.store', $shift), ['tour_id' => $refused->id])
            ->assertSessionHasErrors('tour_id');
    }

    expect(SignUp::count())->toBe(0);
});

it('lets any Member give an open-to-all Tour without a qualification', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights', ['open_to_all' => true]);
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $egypt]));
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $highlights->id])
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($highlights->id);
});

it('fills in the Tour on a one-Tour kind without asking', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights], 'Museum Highlights'));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $highlights);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($highlights->id);
});

it('fills in the one Tour the Member may give on a multi-Tour kind (#803)', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights');
    $trainee = signUpTourTour($group, 'Museum Highlights – New Docents');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $trainee], 'Museum Highlights'));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $highlights);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($highlights->id);
});

it('still refuses a Tour the Member may not give when they may give only one (#803)', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights');
    $trainee = signUpTourTour($group, 'Museum Highlights – New Docents');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $trainee], 'Museum Highlights'));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $highlights);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $trainee->id])
        ->assertSessionHasErrors(['tour_id' => 'You cannot give this tour on this shift.']);

    expect(SignUp::count())->toBe(0);
});

it('ignores a retired Tour on the kind', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights');
    $retired = signUpTourTour($group, 'Old Tour', ['active' => false]);
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $retired]));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $highlights);
    signUpTourQualify($member, $retired);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $retired->id])
        ->assertSessionHasErrors('tour_id');

    // One active Tour left: a one-Tour kind again, filled in.
    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($highlights->id);
});

it('forbids a sign-up where the Member may give none of the kind\'s Tours', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt]));
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $egypt->id])
        ->assertForbidden();

    expect(SignUp::count())->toBe(0);
});

it('signs up as before on a kind with no Tours, and refuses a Tour there', function () {
    $group = signUpTourGroup();
    $tour = signUpTourTour($group, 'Ancient Egypt', ['open_to_all' => true]);
    $shift = signUpTourShift($group, ShiftKind::factory()->create(['group_id' => $group->id]));
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $tour->id])
        ->assertSessionHasErrors('tour_id');

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBeNull();
});

it('signs up as before on a Group without Tours and a kind-less Shift', function () {
    $group = signUpTourGroup();
    $shift = signUpTourShift($group, null);
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', $shift))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBeNull();
});

it('lets a non-member take an open Shift only through an open-to-all Tour', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights', ['open_to_all' => true]);
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $egypt]), ShiftAudience::Open);
    $outsider = signUpTourMember(signUpTourGroup());

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $egypt->id])
        ->assertSessionHasErrors('tour_id');

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', $shift), ['tour_id' => $highlights->id])
        ->assertSessionHasNoErrors();
});

// --- the read: button, picker, and the Tour on the seat -------------------------

it('hides the Sign-up button where the Member may give none of the kind\'s Tours', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt');
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt]));
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.can.signUp', false));

    signUpTourQualify($member, $egypt);

    $this->actingAs($member->fresh())
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.can.signUp', true));
});

it('offers only the Tours the Member may give, and says whether the kind asks', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights', ['open_to_all' => true, 'sort_order' => 2]);
    $egypt = signUpTourTour($group, 'Ancient Egypt', ['sort_order' => 1]);
    $dinos = signUpTourTour($group, 'Dinosaurs', ['sort_order' => 3]);
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $egypt, $dinos]));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $egypt);

    $this->actingAs($member)
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.tour_choice', 'pick')
            ->where('scheduling.open.shifts.0.tours', [
                ['id' => $egypt->id, 'name' => 'Ancient Egypt'],
                ['id' => $highlights->id, 'name' => 'Museum Highlights'],
            ]));
});

it('marks a multi-Tour kind as filled in when the Member may give only one of its Tours (#803)', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights');
    $trainee = signUpTourTour($group, 'Museum Highlights – New Docents');
    $shift = signUpTourShift($group, signUpTourKind($group, [$highlights, $trainee], 'Museum Highlights'));
    $member = signUpTourMember($group);
    signUpTourQualify($member, $highlights);

    $this->actingAs($member)
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.tour_choice', 'fill')
            ->where('scheduling.open.shifts.0.tours', [['id' => $highlights->id, 'name' => 'Museum Highlights']]));
});

it('marks a one-Tour kind as filled in and a Tour-less kind as none', function () {
    $group = signUpTourGroup();
    $highlights = signUpTourTour($group, 'Museum Highlights', ['open_to_all' => true]);
    $one = signUpTourShift($group, signUpTourKind($group, [$highlights], 'Museum Highlights'));
    $member = signUpTourMember($group);

    $this->actingAs($member)
        ->get(signUpTourPage($one))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.tour_choice', 'fill')
            ->where('scheduling.open.shifts.0.tours.0.name', 'Museum Highlights'));

    $none = signUpTourShift($group, ShiftKind::factory()->create(['group_id' => $group->id]));

    $this->actingAs($member)
        ->get(signUpTourPage($none))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.tour_choice', null)
            ->where('scheduling.open.shifts.0.tours', []));
});

it('shows the Tour on the seat in the Agenda and in My sign-ups', function () {
    $group = signUpTourGroup();
    $egypt = signUpTourTour($group, 'Ancient Egypt', ['open_to_all' => true]);
    $shift = signUpTourShift($group, signUpTourKind($group, [$egypt]));
    $member = signUpTourMember($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id, 'tour_id' => $egypt->id]);

    $this->actingAs($member)
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.signups.0.tour', 'Ancient Egypt')
            ->where('scheduling.mine.0.signups.0.tour', 'Ancient Egypt'));
});

it('shows no Tour on a seat that records none', function () {
    $group = signUpTourGroup();
    $shift = signUpTourShift($group, null);
    $member = signUpTourMember($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    $this->actingAs($member)
        ->get(signUpTourPage($shift))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.signups.0.tour', null));
});
