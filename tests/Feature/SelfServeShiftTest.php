<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ShiftAudience;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Support\OrgTime;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A Member writing their own Shift in a self-serve Group (#585, PRD #576, ADR-0026 §1, §2). The
 * write seam — three routes on one controller (self-serve-shifts.store / .update / .destroy),
 * authorized in their Form Requests via the ShiftPolicy's createSelfServe and manageSelfServe —
 * and the Inertia hints that reveal the button and the owner's edit/delete. No Objects and no
 * clash checks here; those are #586 and #588. Prior art: GroupSignUpsTest, GroupSchedulingTest.
 */

// Freeze "now" to a fixed org-clock instant inside the June 2026 schedule: 15 June, 2pm at the
// museum. So today is the 15th, a start earlier today (10am) is still today, the 14th is an
// earlier day, and July is outside the range.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-06-15 14:00:00', config('app.org_timezone'))));

/** A self-serve, org-listed scheduling Group with GI's 45-minute unit. */
function selfServeGroup(array $overrides = []): Group
{
    return Group::factory()->program()->publicListing()->create([
        'self_serve_shifts' => true,
        'self_serve_unit_minutes' => 45,
        ...$overrides,
    ]);
}

/** A published schedule spanning June 2026 on the given Group. */
function juneSchedule(Group $group): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
    ]);
}

/** A Member of the Group with a chosen standing, optionally carrying a role. */
function giMemberOf(
    Group $group,
    MembershipStatus $status = MembershipStatus::Full,
    Category $category = Category::Active,
    ?Role $role = null,
): Member {
    $member = Member::factory()->category($category)->create();
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => $status,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** An active station kind of the Group. */
function stationOf(Group $group, array $overrides = []): ShiftKind
{
    return ShiftKind::factory()->create(['group_id' => $group->id, ...$overrides]);
}

/** The default valid store payload: a future in-range start on the grid, three units. */
function selfServeBody(ShiftKind $station, array $overrides = []): array
{
    return [
        'shift_kind_id' => $station->id,
        'starts_at' => '2026-06-16T10:00',
        'units' => 3,
        ...$overrides,
    ];
}

// --- store: the one-step Shift plus Sign-up -----------------------------------

it('writes a Shift and the author’s Sign-up in one request', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertRedirect();

    $shift = Shift::firstOrFail();
    expect($shift->schedule_id)->toBe($schedule->id)
        ->and($shift->capacity)->toBe(1)
        ->and($shift->audience)->toBe(ShiftAudience::Group)
        ->and($shift->shift_kind_id)->toBe($station->id)
        // ends_at is derived: 3 units × 45 minutes = 2h15m after the start.
        ->and($shift->ends_at->equalTo($shift->starts_at->copy()->addMinutes(135)))->toBeTrue();

    expect(SignUp::where(['shift_id' => $shift->id, 'member_id' => $member->id])->exists())->toBeTrue();
});

it('shows the new Shift on the Agenda with the author’s name', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = Member::factory()->category(Category::Active)->create(['first_name' => 'Rosa', 'last_name' => 'Lin']);
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id, 'status' => MembershipStatus::Full]);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station));

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.open.shifts', 1)
            ->where('scheduling.open.shifts.0.signups.0.first_name', 'Rosa'));
});

it('accepts a start earlier today', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    // 10am today, before the frozen 2pm now — the desk case.
    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['starts_at' => '2026-06-15T10:00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(1);
});

it('rejects a start on an earlier day', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['starts_at' => '2026-06-14T10:00']))
        ->assertSessionHasErrors('starts_at');

    expect(Shift::count())->toBe(0);
});

it('rejects a start outside the schedule range', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['starts_at' => '2026-07-01T10:00']))
        ->assertSessionHasErrors('starts_at');

    expect(Shift::count())->toBe(0);
});

it('rejects a start off the 15-minute grid', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['starts_at' => '2026-06-16T10:07']))
        ->assertSessionHasErrors('starts_at');

    expect(Shift::count())->toBe(0);
});

it('rejects units below 1 and above 8', function (int $units) {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['units' => $units]))
        ->assertSessionHasErrors('units');

    expect(Shift::count())->toBe(0);
})->with([0, 9]);

it('stores ends_at as start plus units × 45 for GI', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['units' => 4]));

    $shift = Shift::firstOrFail();
    expect($shift->ends_at->equalTo($shift->starts_at->copy()->addMinutes(180)))->toBeTrue();
});

it('rejects a kind that is not an active kind of the Group', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $member = giMemberOf($group);

    $retired = stationOf($group, ['active' => false]);
    $otherGroupStation = stationOf(selfServeGroup());

    foreach ([$retired, $otherGroupStation] as $station) {
        $this->actingAs($member)
            ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
            ->assertSessionHasErrors('shift_kind_id');
    }

    expect(Shift::count())->toBe(0);
});

// --- store: the authorization floors ------------------------------------------

it('refuses a store on a draft schedule', function () {
    $group = selfServeGroup();
    $schedule = Schedule::factory()->draft()->create(['group_id' => $group->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30']);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('refuses a store on a Group that is not self-serve', function () {
    $group = selfServeGroup(['self_serve_shifts' => false]);
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('refuses a store by a non-member of the Group', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $outsider = Member::factory()->category(Category::Active)->create();

    $this->actingAs($outsider)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('refuses a store by a member on leave', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group, MembershipStatus::Loa);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

// --- update: the owner changes kind, start and units --------------------------

/** Write a self-serve Shift for the given owner and return it, seat included. */
function ownedShift(Schedule $schedule, ShiftKind $station, Member $owner, array $overrides = []): Shift
{
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => Carbon::parse('2026-06-16 10:00', config('app.org_timezone')),
        'ends_at' => Carbon::parse('2026-06-16 12:15', config('app.org_timezone')),
        ...$overrides,
    ]);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $owner->id]);

    return $shift;
}

it('lets the owner change kind, start and units and re-derives the end', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $other = stationOf($group);
    $owner = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner);

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $other->id,
            'starts_at' => '2026-06-17T13:00',
            'units' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $shift->refresh();
    expect($shift->shift_kind_id)->toBe($other->id)
        ->and($shift->ends_at->equalTo($shift->starts_at->copy()->addMinutes(90)))->toBeTrue();
});

it('refuses an update once the Shift has started', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    // Started yesterday, still in range — the start bound has passed.
    $shift = ownedShift($schedule, $station, $owner, [
        'starts_at' => Carbon::parse('2026-06-14 10:00', config('app.org_timezone')),
        'ends_at' => Carbon::parse('2026-06-14 12:15', config('app.org_timezone')),
    ]);

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-16T10:00',
            'units' => 2,
        ])
        ->assertForbidden();
});

it('refuses an update by another Member', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $intruder = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner);

    $this->actingAs($intruder)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-17T10:00',
            'units' => 2,
        ])
        ->assertForbidden();
});

it('refuses an update on a Shift with capacity above one', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner, ['capacity' => 2]);

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-17T10:00',
            'units' => 2,
        ])
        ->assertForbidden();
});

// --- destroy: the owner deletes, and the Schedulers hear -----------------------

it('deletes the Shift and its Sign-up together', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner);

    $this->actingAs($owner)
        ->delete(route('self-serve-shifts.destroy', ['shift' => $shift->id]))
        ->assertRedirect();

    expect(Shift::whereKey($shift->id)->exists())->toBeFalse()
        ->and(SignUp::where('shift_id', $shift->id)->exists())->toBeFalse();
});

it('writes one cancellation Notice per Scheduler and Chair on delete', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $scheduler = giMemberOf($group, role: Role::Scheduler);
    $chair = giMemberOf($group, role: Role::Chair);
    $shift = ownedShift($schedule, $station, $owner);

    $this->actingAs($owner)
        ->delete(route('self-serve-shifts.destroy', ['shift' => $shift->id]))
        ->assertRedirect();

    $recipients = Delivery::where('kind', DeliveryKind::Notice)->pluck('member_id');
    expect($recipients)->toHaveCount(2)
        ->and($recipients->contains($scheduler->id))->toBeTrue()
        ->and($recipients->contains($chair->id))->toBeTrue();
});

it('refuses a delete once the Shift has started', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner, [
        'starts_at' => Carbon::parse('2026-06-14 10:00', config('app.org_timezone')),
        'ends_at' => Carbon::parse('2026-06-14 12:15', config('app.org_timezone')),
    ]);

    $this->actingAs($owner)
        ->delete(route('self-serve-shifts.destroy', ['shift' => $shift->id]))
        ->assertForbidden();

    expect(Shift::whereKey($shift->id)->exists())->toBeTrue();
});

// --- store / update: the station clash warning (#588, ADR-0026 §5) ------------

/**
 * Another Member's seat on a Shift of the given kind over the given org-clock window. The times
 * are stored as UTC instants the way the controller stores them (via {@see OrgTime::toUtc}), so
 * the overlap check reads the seat on the same basis as a candidate the store derives.
 */
function seatOnStation(Schedule $schedule, ShiftKind $station, string $startsAt, string $endsAt): Shift
{
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => OrgTime::toUtc($startsAt),
        'ends_at' => OrgTime::toUtc($endsAt),
    ]);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => giMemberOf($schedule->group)->id]);

    return $shift;
}

it('warns on the distinct key when another seat overlaps the same station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    // An existing seat 11:00–12:00 overlaps the default 10:00–12:15 candidate on the same station.
    seatOnStation($schedule, $station, '2026-06-16 11:00', '2026-06-16 12:00');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertSessionHasErrors(['shift_kind_id' => trans('group.scheduling_panel.self_serve.station_clash')]);

    expect(Shift::where('schedule_id', $schedule->id)->count())->toBe(1);
});

it('warns on an exact same start on the same station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    // Same start as the 10:00 candidate, on the same station.
    seatOnStation($schedule, $station, '2026-06-16T10:00', '2026-06-16T10:45');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertSessionHasErrors('shift_kind_id');
});

it('warns on a 30-minute overlap on the same station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    // Starts 30 minutes before the 10:00 candidate and runs into it — the case exact-start misses.
    seatOnStation($schedule, $station, '2026-06-16T09:30', '2026-06-16T10:30');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertSessionHasErrors('shift_kind_id');
});

it('does not warn about a seat on a different station at the same time', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $otherStation = stationOf($group);
    $member = giMemberOf($group);
    // Overlaps the candidate in time, but a different station — a different place on the floor.
    seatOnStation($schedule, $otherStation, '2026-06-16T10:00', '2026-06-16T11:00');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('does not warn about a back-to-back seat on the same station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    // Starts exactly when the 10:00–12:15 candidate ends — touching ends do not overlap.
    seatOnStation($schedule, $station, '2026-06-16T12:15', '2026-06-16T13:00');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('lands the Shift when the clash is acknowledged', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    seatOnStation($schedule, $station, '2026-06-16 11:00', '2026-06-16 12:00');

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), selfServeBody($station, ['acknowledge_station_clash' => true]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::where('schedule_id', $schedule->id)->count())->toBe(2);
});

it('warns when an update overlaps another seat on the station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $shift = ownedShift($schedule, $station, $owner);
    // Another interpreter's seat the edit will overlap once the start moves to 11:00.
    seatOnStation($schedule, $station, '2026-06-16T11:00', '2026-06-16T12:00');

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-16T11:00',
            'units' => 2,
        ])
        ->assertSessionHasErrors(['shift_kind_id' => trans('group.scheduling_panel.self_serve.station_clash')]);
});

it('does not warn about the Shift’s own seat when the owner edits it', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    // The owner's own 10:00–12:15 seat is the only one on the station; shrinking the units keeps
    // the same start, so it would overlap itself if its own seat were not excluded.
    $shift = ownedShift($schedule, $station, $owner);

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-16T10:00',
            'units' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('never warns when a Member takes a Scheduler-authored seat on the station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $member = giMemberOf($group);
    // The Scheduler-authored Shift the Member takes, and another same-kind seat it overlaps.
    $target = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => OrgTime::toUtc('2026-06-16T10:00'),
        'ends_at' => OrgTime::toUtc('2026-06-16T11:00'),
    ]);
    seatOnStation($schedule, $station, '2026-06-16T10:00', '2026-06-16T11:00');

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $target->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SignUp::where(['shift_id' => $target->id, 'member_id' => $member->id])->exists())->toBeTrue();
});

it('never warns when a Scheduler places a Member on the station', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $scheduler = giMemberOf($group, role: Role::Scheduler);
    $placed = giMemberOf($group);
    $target = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => OrgTime::toUtc('2026-06-16T10:00'),
        'ends_at' => OrgTime::toUtc('2026-06-16T11:00'),
    ]);
    seatOnStation($schedule, $station, '2026-06-16T10:00', '2026-06-16T11:00');

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $target->id]), ['member_id' => $placed->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SignUp::where(['shift_id' => $target->id, 'member_id' => $placed->id])->exists())->toBeTrue();
});

// --- Inertia hints: the button and the owner’s controls -----------------------

it('offers Write my shift to a self-serve GI member', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.can.createSelfServe', true));
});

it('offers no Write my shift button on a Group that is not self-serve', function () {
    $group = selfServeGroup(['self_serve_shifts' => false]);
    $schedule = juneSchedule($group);
    $member = giMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.can.createSelfServe', false));
});

it('marks the author’s own Shift manageable and no one else’s', function () {
    $group = selfServeGroup();
    $schedule = juneSchedule($group);
    $station = stationOf($group);
    $owner = giMemberOf($group);
    $other = giMemberOf($group);
    ownedShift($schedule, $station, $owner);

    $this->actingAs($owner)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.can.manageSelfServe', true));

    $this->actingAs($other)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.can.manageSelfServe', false));
});
