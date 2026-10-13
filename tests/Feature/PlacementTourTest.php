<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
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
 * Officer placement and changing a Sign-up's Tour (#791, ADR-0033 §6). A Scheduler places any
 * Member on a Tour-mapped Shift with no qualification check, picking any of the kind's active
 * Tours or none. Bulk placement fills the Tour on a one-Tour kind and leaves it blank on a
 * multi-Tour kind. A Member changes the Tour on their own Sign-up until the Shift starts, under
 * the self sign-up check; a Scheduler changes it on any Sign-up, any time.
 */

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-06-15 14:00:00', config('app.org_timezone'))));

function placementTourGroup(): Group
{
    return Group::factory()->program()->publicListing()->create([
        'has_scheduling' => true,
        'has_vetting' => true,
    ]);
}

function placementTourMember(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->category(Category::Active)->create();
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

function placementTourTour(Group $group, string $name, array $overrides = []): Tour
{
    return Tour::factory()->create(['group_id' => $group->id, 'name' => $name, ...$overrides]);
}

function placementTourKind(Group $group, array $tours): ShiftKind
{
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);
    $kind->tours()->attach(collect($tours)->pluck('id'));

    return $kind;
}

function placementTourQualify(Member $member, Tour $tour): void
{
    Qualification::factory()->create([
        'group_member_id' => $member->membershipIn($tour->group)->id,
        'tour_id' => $tour->id,
        'active' => true,
    ]);
}

function placementTourSchedule(Group $group): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
    ]);
}

/** A two-seat Shift on the kind, on the given museum day at 10:00–11:00 (tomorrow by default). */
function placementTourShift(Schedule $schedule, ?ShiftKind $kind, string $date = '2026-06-16'): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind?->id,
        'capacity' => 2,
        'starts_at' => Carbon::parse("{$date} 10:00", config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse("{$date} 11:00", config('app.org_timezone'))->utc(),
    ]);
}

// --- Single placement --------------------------------------------------------------

it('places an unqualified Member on any of the kind\'s active Tours', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $dinos = placementTourTour($group, 'Dinosaurs');
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $dinos]));
    $scheduler = placementTourMember($group, Role::Scheduler);
    $member = placementTourMember($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', $shift), ['member_id' => $member->id, 'tour_id' => $dinos->id])
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($dinos->id);
});

it('places a Member with a blank Tour on a Tour kind', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt]));
    $scheduler = placementTourMember($group, Role::Scheduler);
    $member = placementTourMember($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', $shift), ['member_id' => $member->id, 'tour_id' => null])
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBeNull();
});

it('refuses a placement on a retired or unmapped Tour, and any Tour on a Tour-less Shift', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $retired = placementTourTour($group, 'Old', ['active' => false]);
    $unmapped = placementTourTour($group, 'Unmapped');
    $schedule = placementTourSchedule($group);
    $shift = placementTourShift($schedule, placementTourKind($group, [$egypt, $retired]));
    $plain = placementTourShift($schedule, null);
    $scheduler = placementTourMember($group, Role::Scheduler);
    $member = placementTourMember($group);

    foreach ([$retired, $unmapped] as $tour) {
        $this->actingAs($scheduler)
            ->post(route('assignments.store', $shift), ['member_id' => $member->id, 'tour_id' => $tour->id])
            ->assertSessionHasErrors(['tour_id' => 'This tour is not given on this shift.']);
    }

    $this->actingAs($scheduler)
        ->post(route('assignments.store', $plain), ['member_id' => $member->id, 'tour_id' => $egypt->id])
        ->assertSessionHasErrors('tour_id');

    expect(SignUp::count())->toBe(0);
});

it('offers the Scheduler every active Tour of the kind in the placement payload', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt', ['sort_order' => 1]);
    $dinos = placementTourTour($group, 'Dinosaurs', ['sort_order' => 2]);
    $retired = placementTourTour($group, 'Old', ['active' => false, 'sort_order' => 3]);
    $schedule = placementTourSchedule($group);
    placementTourShift($schedule, placementTourKind($group, [$egypt, $dinos, $retired]));
    $scheduler = placementTourMember($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.tours_offered', [
                ['id' => $egypt->id, 'name' => 'Ancient Egypt'],
                ['id' => $dinos->id, 'name' => 'Dinosaurs'],
            ]));
});

it('withholds the officer Tour list from an ordinary Member', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $schedule = placementTourSchedule($group);
    placementTourShift($schedule, placementTourKind($group, [$egypt]));
    $member = placementTourMember($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.tours_offered', []));
});

// --- Bulk placement ----------------------------------------------------------------

function placementTourBulk(Member $member): array
{
    return [
        'member_id' => $member->id,
        'starts_time' => '10:00',
        'ends_time' => '11:00',
        'days_of_week' => [2],
        'from_date' => '2026-06-01',
        'to_date' => '2026-06-30',
        'interval' => 'weekly',
        'anchor_date' => '2026-06-02',
    ];
}

it('fills the Tour in a bulk placement on a one-Tour kind, unqualified Member included', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $schedule = placementTourSchedule($group);
    placementTourShift($schedule, placementTourKind($group, [$egypt]), '2026-06-16');
    placementTourShift($schedule, placementTourKind($group, [$egypt]), '2026-06-23');
    $scheduler = placementTourMember($group, Role::Scheduler);
    $member = placementTourMember($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), placementTourBulk($member))
        ->assertSessionHasNoErrors();

    expect(SignUp::pluck('tour_id')->all())->toBe([$egypt->id, $egypt->id]);
});

it('leaves the Tour blank in a bulk placement on a multi-Tour kind and on a Tour-less Shift', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $dinos = placementTourTour($group, 'Dinosaurs');
    $schedule = placementTourSchedule($group);
    placementTourShift($schedule, placementTourKind($group, [$egypt, $dinos]), '2026-06-16');
    placementTourShift($schedule, null, '2026-06-23');
    $scheduler = placementTourMember($group, Role::Scheduler);
    $member = placementTourMember($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), placementTourBulk($member))
        ->assertSessionHasNoErrors();

    expect(SignUp::count())->toBe(2)
        ->and(SignUp::whereNotNull('tour_id')->count())->toBe(0);
});

// --- Changing the Tour on a Sign-up -----------------------------------------------

/** Seat the Member on the Shift with the given Tour. */
function placementTourSeat(Shift $shift, Member $member, ?Tour $tour): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id, 'tour_id' => $tour?->id]);
}

it('lets a Member change their own Tour to another they may give before the Shift starts', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $dinos = placementTourTour($group, 'Dinosaurs');
    $family = placementTourTour($group, 'Family', ['open_to_all' => true]);
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $dinos, $family]));
    $member = placementTourMember($group);
    placementTourQualify($member, $dinos);
    $signUp = placementTourSeat($shift, $member, $family);

    $this->actingAs($member->fresh())
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $dinos->id])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->tour_id)->toBe($dinos->id);
});

it('refuses a Member a Tour they may not give, or a blank Tour', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $family = placementTourTour($group, 'Family', ['open_to_all' => true]);
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $family]));
    $member = placementTourMember($group);
    $signUp = placementTourSeat($shift, $member, $family);

    $this->actingAs($member)
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $egypt->id])
        ->assertSessionHasErrors(['tour_id' => 'You cannot give this tour on this shift.']);

    $this->actingAs($member)
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => null])
        ->assertSessionHasErrors(['tour_id' => 'Choose the tour you will give.']);

    expect($signUp->fresh()->tour_id)->toBe($family->id);
});

it('refuses a Member changing their own Tour once the Shift has started', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt', ['open_to_all' => true]);
    $family = placementTourTour($group, 'Family', ['open_to_all' => true]);
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $family]), '2026-06-15');
    $member = placementTourMember($group);
    $signUp = placementTourSeat($shift, $member, $family);

    $this->actingAs($member)
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $egypt->id])
        ->assertForbidden();

    expect($signUp->fresh()->tour_id)->toBe($family->id);
});

it('refuses a Member changing the Tour on someone else\'s Sign-up', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt', ['open_to_all' => true]);
    $family = placementTourTour($group, 'Family', ['open_to_all' => true]);
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $family]));
    $signUp = placementTourSeat($shift, placementTourMember($group), $family);

    $this->actingAs(placementTourMember($group))
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $egypt->id])
        ->assertForbidden();
});

it('lets a Scheduler set any active Tour of the kind or blank on any Sign-up, after the start too', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $dinos = placementTourTour($group, 'Dinosaurs');
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt, $dinos]), '2026-06-15');
    $scheduler = placementTourMember($group, Role::Scheduler);
    $signUp = placementTourSeat($shift, placementTourMember($group), null);

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $dinos->id])
        ->assertSessionHasNoErrors();
    expect($signUp->fresh()->tour_id)->toBe($dinos->id);

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => null])
        ->assertSessionHasNoErrors();
    expect($signUp->fresh()->tour_id)->toBeNull();
});

it('refuses a Scheduler a retired or unmapped Tour, and any Tour on a Tour-less Shift', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $retired = placementTourTour($group, 'Old', ['active' => false]);
    $unmapped = placementTourTour($group, 'Unmapped');
    $schedule = placementTourSchedule($group);
    $shift = placementTourShift($schedule, placementTourKind($group, [$egypt, $retired]));
    $plain = placementTourShift($schedule, null);
    $scheduler = placementTourMember($group, Role::Scheduler);
    $signUp = placementTourSeat($shift, placementTourMember($group), $egypt);
    $plainSeat = placementTourSeat($plain, placementTourMember($group), null);

    foreach ([$retired, $unmapped] as $tour) {
        $this->actingAs($scheduler)
            ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $tour->id])
            ->assertSessionHasErrors(['tour_id' => 'This tour is not given on this shift.']);
    }

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.tour.update', $plainSeat), ['tour_id' => $egypt->id])
        ->assertSessionHasErrors(['tour_id' => 'This shift has no tours.']);

    expect($signUp->fresh()->tour_id)->toBe($egypt->id);
});

it('refuses a Scheduler of another Group', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt]));
    $signUp = placementTourSeat($shift, placementTourMember($group), null);

    $this->actingAs(placementTourMember(placementTourGroup(), Role::Scheduler))
        ->patch(route('sign-ups.tour.update', $signUp), ['tour_id' => $egypt->id])
        ->assertForbidden();
});

it('tells each seat whether the viewer may change its Tour', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt', ['open_to_all' => true]);
    $schedule = placementTourSchedule($group);
    $shift = placementTourShift($schedule, placementTourKind($group, [$egypt]));
    $member = placementTourMember($group);
    $other = placementTourMember($group);
    placementTourSeat($shift, $member, $egypt);
    placementTourSeat($shift, $other, null);
    $scheduler = placementTourMember($group, Role::Scheduler);
    $page = route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]);

    $this->actingAs($member)->get($page)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.can_change_tour', true)
        ->where('scheduling.open.shifts.0.signups.0.tour_id', $egypt->id)
        ->where('scheduling.open.shifts.0.signups.1.can_change_tour', false));

    $this->actingAs($scheduler)->get($page)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.can_change_tour', true)
        ->where('scheduling.open.shifts.0.signups.1.can_change_tour', true));

    $this->travelTo(Carbon::parse('2026-06-16 10:30', config('app.org_timezone')));

    $this->actingAs($member)->get($page)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.can_change_tour', false));
});

it('offers no Tour change on a Tour-less Shift', function () {
    $group = placementTourGroup();
    $schedule = placementTourSchedule($group);
    $shift = placementTourShift($schedule, null);
    $scheduler = placementTourMember($group, Role::Scheduler);
    placementTourSeat($shift, placementTourMember($group), null);

    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.signups.0.can_change_tour', false));
});

// --- Changing a Shift's kind -------------------------------------------------------

it('clears the Tour on Sign-ups whose Tour the Shift\'s new kind does not offer', function () {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $dinos = placementTourTour($group, 'Dinosaurs');
    $retired = placementTourTour($group, 'Old', ['active' => false]);
    $schedule = placementTourSchedule($group);
    $shift = placementTourShift($schedule, placementTourKind($group, [$egypt, $dinos]));
    $newKind = placementTourKind($group, [$dinos, $retired]);
    $keeps = SignUp::factory()->create(['shift_id' => $shift->id, 'tour_id' => $dinos->id]);
    $loses = SignUp::factory()->create(['shift_id' => $shift->id, 'tour_id' => $egypt->id]);
    $other = SignUp::factory()->create([
        'shift_id' => placementTourShift($schedule, placementTourKind($group, [$egypt]))->id,
        'tour_id' => $egypt->id,
    ]);

    $this->actingAs(placementTourMember($group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['shift_kind_id' => $newKind->id])
        ->assertSessionHasNoErrors();

    expect($keeps->fresh()->tour_id)->toBe($dinos->id)
        ->and($loses->fresh()->tour_id)->toBeNull()
        ->and($other->fresh()->tour_id)->toBe($egypt->id);
});

it('clears every Tour when the Shift moves to a Tour-less kind or to no kind', function (bool $toNoKind) {
    $group = placementTourGroup();
    $egypt = placementTourTour($group, 'Ancient Egypt');
    $shift = placementTourShift(placementTourSchedule($group), placementTourKind($group, [$egypt]));
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'tour_id' => $egypt->id]);
    $plain = ShiftKind::factory()->create(['group_id' => $group->id]);

    $this->actingAs(placementTourMember($group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['shift_kind_id' => $toNoKind ? null : $plain->id])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->tour_id)->toBeNull();
})->with([false, true]);
