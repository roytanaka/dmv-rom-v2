<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ShiftAudience;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HandlingObject;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Objects on seats and the double-booking block (#586, PRD #576, ADR-0026 §3) — a Gallery
 * Interpreter picks the Objects they take when they write a shift, take an event seat, or are
 * placed by the Scheduler. The seat carries them; the server refuses an Object another Sign-up
 * already holds at an overlapping time. One `objects` field and one clash validator across four
 * write seams (self-serve-shifts.store / .update, sign-ups.store, assignments.store).
 */

// Freeze "now" inside the June 2026 schedule, 15 June 2pm at the museum — the same frame the
// self-serve tests use, so a start earlier today is still today and July is out of range.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-06-15 14:00:00', config('app.org_timezone'))));

/** A self-serve, org-listed scheduling Group with GI's 45-minute unit. */
function objectsGroup(array $overrides = []): Group
{
    return Group::factory()->program()->publicListing()->create([
        'self_serve_shifts' => true,
        'self_serve_unit_minutes' => 45,
        ...$overrides,
    ]);
}

/** A published June 2026 schedule on the Group. */
function objectsSchedule(Group $group): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
    ]);
}

/** A Member of the Group, optionally with a role. */
function objectsMember(Group $group, ?Role $role = null): Member
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

/** An active station kind of the Group. */
function objectsStation(Group $group): ShiftKind
{
    return ShiftKind::factory()->create(['group_id' => $group->id]);
}

/** An Object of the Group's handling collection. */
function handlingObject(Group $group, string $name = 'Owl Skull', bool $active = true): HandlingObject
{
    return HandlingObject::factory()->create(['group_id' => $group->id, 'name' => $name, 'active' => $active]);
}

/**
 * A self-serve Shift seated by the owner, with the given Objects reserved. Times are museum
 * wall-clock strings, stored as the true UTC instants a self-serve write would store (via the org
 * zone), so the seeded hold and a request-derived candidate compare on one clock.
 */
function seatedShift(Schedule $schedule, ShiftKind $station, Member $owner, array $objects, string $start = '2026-06-16 10:00', string $end = '2026-06-16 12:15'): Shift
{
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => Carbon::parse($start, config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse($end, config('app.org_timezone'))->utc(),
    ]);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $owner->id]);
    $signUp->objects()->attach(collect($objects)->pluck('id'));

    return $shift;
}

/** The default self-serve store payload with the given Object ids. */
function objectsBody(ShiftKind $station, array $objectIds, array $overrides = []): array
{
    return [
        'shift_kind_id' => $station->id,
        'starts_at' => '2026-06-16T10:00',
        'units' => 3,
        'objects' => $objectIds,
        ...$overrides,
    ];
}

// --- self-serve store: the seat carries its Objects ---------------------------

it('stores the Objects a self-serve Shift reserves and shows them on the Agenda seat', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $member = objectsMember($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id, $egg->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signUp = SignUp::firstOrFail();
    expect($signUp->objects()->pluck('objects.id')->all())->toEqualCanonicalizing([$owl->id, $egg->id]);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.open.shifts.0.signups.0.objects', 2)
            ->where('scheduling.open.shifts.0.signups.0.objects.0.name', 'Owl Skull'));
});

it('refuses a self-serve store with no Object when the Group has active Objects', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    handlingObject($group);
    $member = objectsMember($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, []))
        ->assertSessionHasErrors('objects');

    expect(Shift::count())->toBe(0);
});

it('does not require an Object on a Group with no active Objects', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    // A retired Object is present but none active — the field is not required.
    handlingObject($group, 'Retired Thing', active: false);
    $member = objectsMember($group);

    $this->actingAs($member)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, []))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(1);
});

it('refuses a retired or foreign Object on a self-serve store', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    handlingObject($group, 'Active One');
    $retired = handlingObject($group, 'Retired One', active: false);
    $foreign = handlingObject(objectsGroup(), 'Other Group Object');
    $member = objectsMember($group);

    foreach ([$retired->id, $foreign->id] as $bad) {
        $this->actingAs($member)
            ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$bad]))
            ->assertSessionHasErrors('objects.0');
    }

    expect(Shift::count())->toBe(0);
});

// --- the double-booking block -------------------------------------------------

it('refuses an Object another Sign-up holds at an overlapping time, naming it and the other time', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    // Someone already holds the Owl Skull on a 10:00–12:15 Shift on 16 June.
    seatedShift($schedule, $station, $holder, [$owl]);

    // A new shift starting 11:00 the same day overlaps that hold.
    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-16T11:00']))
        ->assertSessionHasErrors('objects');

    $error = session('errors')->first('objects');
    expect($error)->toContain('Owl Skull');

    expect(Shift::count())->toBe(1);
});

it('accepts the same Object back to back — touching ends do not clash', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    // The holder's Shift runs 10:00–10:45 (one unit); the newcomer starts exactly at 10:45.
    seatedShift($schedule, $station, $holder, [$owl], '2026-06-16 10:00', '2026-06-16 10:45');

    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-16T10:45', 'units' => 1]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(2);
});

it('lets a different Object share the overlapping time', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    seatedShift($schedule, $station, $holder, [$owl]);

    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$egg->id], ['starts_at' => '2026-06-16T11:00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(2);
});

// --- the off-site hold: an Object is held a day either side (ADR-0026 §4) -----

/** An active off-site station kind of the Group — its holds widen a day each side. */
function offSiteStation(Group $group): ShiftKind
{
    return ShiftKind::factory()->create(['group_id' => $group->id, 'off_site' => true]);
}

it('holds an off-site Object from the day before through the day after, refusing an overlapping seat', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $offSite = offSiteStation($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    // The Owl is out on an off-site event on 17 June (D). Its hold runs 16 June through 18 June.
    seatedShift($schedule, $offSite, $holder, [$owl], '2026-06-17 10:00', '2026-06-17 12:15');

    // A gallery seat the day before (D−1) is refused the Owl.
    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-16T10:00']))
        ->assertSessionHasErrors('objects');

    // A gallery seat the day after (D+1) is refused it too.
    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-18T10:00']))
        ->assertSessionHasErrors('objects');

    expect(Shift::count())->toBe(1);
});

it('frees an off-site Object two days out, accepting a seat on D−2 and D+2', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $offSite = offSiteStation($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    // The Owl is out on an off-site event on 17 June (D); the hold stops at the end of 18 June.
    seatedShift($schedule, $offSite, $holder, [$owl], '2026-06-17 10:00', '2026-06-17 12:15');

    // Two days before (D−2, today) and two days after (D+2) are outside the hold.
    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-15T10:00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($station, [$owl->id], ['starts_at' => '2026-06-19T10:00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(3);
});

it('refuses an off-site seat an Object held on an in-building seat the day before', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $offSite = offSiteStation($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $newcomer = objectsMember($group);

    // The Owl is held on a gallery seat on 16 June (D−1) — an ordinary same-day hold.
    seatedShift($schedule, $station, $holder, [$owl], '2026-06-16 10:00', '2026-06-16 12:15');

    // An off-site seat on 17 June (D) widens to cover 16 June, so it cannot take the Owl.
    $this->actingAs($newcomer)
        ->post(route('self-serve-shifts.store', ['schedule' => $schedule->id]), objectsBody($offSite, [$owl->id], ['starts_at' => '2026-06-17T10:00']))
        ->assertSessionHasErrors('objects');

    expect(Shift::count())->toBe(1);
});

it('applies the widened hold when a GI takes an off-site event seat', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $offSite = offSiteStation($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $taker = objectsMember($group);

    // The Owl is held on a gallery seat on 16 June; the event runs 17 June.
    seatedShift($schedule, $station, $holder, [$owl], '2026-06-16 10:00', '2026-06-16 12:15');
    $event = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $offSite->id,
        'capacity' => 1,
        'audience' => ShiftAudience::Group,
        'starts_at' => Carbon::parse('2026-06-17 10:00', config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse('2026-06-17 12:15', config('app.org_timezone'))->utc(),
    ]);

    // Taking the event seat with the Owl is refused: the event's hold widens to cover 16 June.
    $this->actingAs($taker)
        ->post(route('sign-ups.store', ['shift' => $event->id]), ['objects' => [$owl->id]])
        ->assertSessionHasErrors('objects');

    expect(SignUp::where('shift_id', $event->id)->count())->toBe(0);
});

it('lets two seats on one off-site Shift each carry their own Object', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $offSite = offSiteStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $scheduler = objectsMember($group, Role::Scheduler);
    $first = objectsMember($group);
    $second = objectsMember($group);

    // A two-seat Scheduler-authored event seat, the shape §8 gives an off-site event.
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $offSite->id,
        'capacity' => 2,
        'audience' => ShiftAudience::Group,
        'starts_at' => Carbon::parse('2026-06-17 10:00', config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse('2026-06-17 12:15', config('app.org_timezone'))->utc(),
    ]);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $first->id, 'objects' => [$owl->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $second->id, 'objects' => [$egg->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstSeat = SignUp::where(['shift_id' => $shift->id, 'member_id' => $first->id])->firstOrFail();
    $secondSeat = SignUp::where(['shift_id' => $shift->id, 'member_id' => $second->id])->firstOrFail();
    expect($firstSeat->objects()->pluck('objects.id')->all())->toBe([$owl->id])
        ->and($secondSeat->objects()->pluck('objects.id')->all())->toBe([$egg->id]);
});

// --- self-serve update: Objects replaced whole, own seat excluded from the clash ---

it('replaces the Objects on an owner’s update', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $owner = objectsMember($group);
    $shift = seatedShift($schedule, $station, $owner, [$owl]);

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-17T10:00',
            'units' => 2,
            'objects' => [$egg->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signUp = SignUp::where('shift_id', $shift->id)->firstOrFail();
    expect($signUp->objects()->pluck('objects.id')->all())->toBe([$egg->id]);
});

it('accepts an owner keeping their own Object on an update', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $owner = objectsMember($group);
    $shift = seatedShift($schedule, $station, $owner, [$owl]);

    // Same Object, edited units — the owner's own seat must not clash with itself.
    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-16T10:00',
            'units' => 2,
            'objects' => [$owl->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('refuses an owner taking an Object another Sign-up holds at an overlapping time on update', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $holder = objectsMember($group);
    $owner = objectsMember($group);

    // Another GI holds the Egg 10:00–12:15 on 16 June.
    seatedShift($schedule, $station, $holder, [$egg]);
    // The owner's own Shift overlaps it, currently holding only the Owl.
    $shift = seatedShift($schedule, $station, $owner, [$owl], '2026-06-16 11:00', '2026-06-16 11:45');

    $this->actingAs($owner)
        ->patch(route('self-serve-shifts.update', ['shift' => $shift->id]), [
            'shift_kind_id' => $station->id,
            'starts_at' => '2026-06-16T11:00',
            'units' => 1,
            'objects' => [$egg->id],
        ])
        ->assertSessionHasErrors('objects');
});

// --- take (sign-ups.store): a Scheduler-authored Shift, the taker picks Objects ---

/** An empty Scheduler-authored Shift on the Group's schedule, 16 June 10:00–12:15. */
function authoredShift(Schedule $schedule, ShiftKind $station, int $capacity = 1): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $station->id,
        'capacity' => $capacity,
        'audience' => ShiftAudience::Group,
        'starts_at' => Carbon::parse('2026-06-16 10:00', config('app.org_timezone'))->utc(),
        'ends_at' => Carbon::parse('2026-06-16 12:15', config('app.org_timezone'))->utc(),
    ]);
}

it('stores the Objects a take reserves', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $member = objectsMember($group);
    $shift = authoredShift($schedule, $station);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]), ['objects' => [$owl->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signUp = SignUp::where(['shift_id' => $shift->id, 'member_id' => $member->id])->firstOrFail();
    expect($signUp->objects()->pluck('objects.id')->all())->toBe([$owl->id]);
});

it('refuses a take with no Object when the Group has active Objects', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    handlingObject($group);
    $member = objectsMember($group);
    $shift = authoredShift($schedule, $station);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]), ['objects' => []])
        ->assertSessionHasErrors('objects');

    expect(SignUp::count())->toBe(0);
});

it('refuses a take of an Object another Sign-up holds at an overlapping time', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $holder = objectsMember($group);
    $member = objectsMember($group);
    seatedShift($schedule, $station, $holder, [$owl]);
    $shift = authoredShift($schedule, $station);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]), ['objects' => [$owl->id]])
        ->assertSessionHasErrors('objects');

    expect(SignUp::where('shift_id', $shift->id)->count())->toBe(0);
});

// --- place (assignments.store): the Scheduler sets a placed Member's Objects ---

it('stores the Objects a placement reserves, and two seats on one Shift carry their own', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $egg = handlingObject($group, 'Ostrich Egg');
    $scheduler = objectsMember($group, Role::Scheduler);
    $first = objectsMember($group);
    $second = objectsMember($group);
    $shift = authoredShift($schedule, $station, capacity: 2);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $first->id, 'objects' => [$owl->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $second->id, 'objects' => [$egg->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstSeat = SignUp::where(['shift_id' => $shift->id, 'member_id' => $first->id])->firstOrFail();
    $secondSeat = SignUp::where(['shift_id' => $shift->id, 'member_id' => $second->id])->firstOrFail();
    expect($firstSeat->objects()->pluck('objects.id')->all())->toBe([$owl->id])
        ->and($secondSeat->objects()->pluck('objects.id')->all())->toBe([$egg->id]);
});

it('refuses placing a Member on an Object a seat on the same Shift already holds', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $scheduler = objectsMember($group, Role::Scheduler);
    $first = objectsMember($group);
    $second = objectsMember($group);
    $shift = authoredShift($schedule, $station, capacity: 2);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $first->id, 'objects' => [$owl->id]]);

    // The same Shift fully overlaps itself, so the second seat cannot take the same Object.
    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $second->id, 'objects' => [$owl->id]])
        ->assertSessionHasErrors('objects');
});

// --- retired Objects keep their name; the picker ships active ones ---

it('still names a retired Object on an old seat', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    $station = objectsStation($group);
    $owl = handlingObject($group, 'Owl Skull');
    $owner = objectsMember($group);
    seatedShift($schedule, $station, $owner, [$owl]);

    // The Scheduler retires the Object after the seat was written.
    $owl->update(['active' => false]);

    $this->actingAs($owner)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.signups.0.objects.0.name', 'Owl Skull'));
});

it('ships only active Objects to the picker, in sort order', function () {
    $group = objectsGroup();
    $schedule = objectsSchedule($group);
    handlingObject($group, 'Zebra Hoof');
    handlingObject($group, 'Owl Skull');
    handlingObject($group, 'Retired Thing', active: false);
    // Put Owl Skull first in the Group's picker order.
    $group->objects()->where('name', 'Owl Skull')->update(['sort_order' => 0]);
    $group->objects()->where('name', 'Zebra Hoof')->update(['sort_order' => 1]);
    $member = objectsMember($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.objects', 2)
            ->where('scheduling.objects.0.name', 'Owl Skull')
            ->where('scheduling.objects.1.name', 'Zebra Hoof'));
});
