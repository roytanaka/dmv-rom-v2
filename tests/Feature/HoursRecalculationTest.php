<?php

use App\Enums\Role;
use App\Enums\ShiftAudience;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursAdjustment;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Carbon\CarbonImmutable;

/*
 * Recalculate scheduled hours from Sign-ups (#410, PRD #406, ADR-0022 §2). A Chair or
 * Scheduler of a scheduling Group opens a month and runs recalculate: for every Member
 * holding a Sign-up on a past Shift in that month, the Shift durations are summed, the
 * Group's `hours_multiplier` is applied once, and the result *replaces* the Member's
 * scheduled hours for the month. It is idempotent, leaves extra hours untouched, is bounded
 * to the current fiscal year, appends no adjustment, and hangs off the scheduling capability.
 *
 * Time is frozen mid-month so a month holds both worked and not-yet-worked Shifts, and the
 * fiscal-year boundary is stable.
 */

/** The frozen wall-clock instant every test runs at: mid-August 2026 (fiscal year 2027). */
function recalcNow(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 8, 15, 12, 0, 0, 'America/Toronto');
}

/** The month under recalculation — the frozen month. */
const RECALC_MONTH = '202608';

/** A Group that runs scheduling, listed org-wide, at the given walks-to-hours multiplier. */
function recalcGroup(int $multiplier = 1): Group
{
    return Group::factory()->publicListing()->create([
        'has_scheduling' => true,
        'hours_multiplier' => $multiplier,
    ]);
}

/** A member of the Group carrying the given role (Scheduler by default). */
function recalcOfficer(Group $group, Role $role = Role::Scheduler): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/**
 * Seat a Member on a fresh Shift of the given whole-hour length, ending at the given
 * org-local datetime. Ending strictly before the frozen "now" makes it a past Shift.
 * Pass {@see ShiftAudience::Open} to model a Shift the whole org may take.
 */
function recalcSignUp(Group $group, Member $member, string $endsAtLocal, int $lengthHours, ShiftAudience $audience = ShiftAudience::Group): SignUp
{
    $endsAt = CarbonImmutable::parse($endsAtLocal, 'America/Toronto');
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => $endsAt->subHours($lengthHours)->utc(),
        'ends_at' => $endsAt->utc(),
        'audience' => $audience,
    ]);

    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

beforeEach(function () {
    $this->travelTo(recalcNow());
});

it('writes each Member their summed past-Shift durations as scheduled hours', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    // Two worked Shifts this month: 3h + 2h = 5 scheduled hours.
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);
    recalcSignUp($group, $worker, '2026-08-10 12:00', 2);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $record = HoursRecord::where('member_id', $worker->id)->where('group_id', $group->id)->sole();
    expect($record->year_month)->toBe(RECALC_MONTH)
        ->and($record->meeting_id)->toBe(HoursRecord::NO_MEETING)
        ->and($record->scheduled_hours)->toBe(5)
        ->and($record->total_hours)->toBe(5);
});

it('applies the Group hours multiplier once (Walker doubles a 3h Shift to 6)', function () {
    $group = recalcGroup(multiplier: 2);
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertRedirect();

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(6);
});

it('counts only past Shifts, never one still to be worked this month', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);   // worked: ended before now
    recalcSignUp($group, $worker, '2026-08-20 13:00', 4);   // future: ends after the frozen now

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertRedirect();

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(3);
});

it('is idempotent — re-running produces the same numbers, not doubled ones', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);
    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(3);
});

it('restates a Member whose Sign-up was removed back to zero, never leaving it stale', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    $signUp = recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);
    $signUp->delete();
    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(0);
});

it('leaves a Member\'s extra hours untouched and keeps total equal to scheduled plus extra', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    HoursRecord::enterExtra($worker, $group, RECALC_MONTH, 4, $worker);
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);

    $record = HoursRecord::where('member_id', $worker->id)->sole();
    expect($record->extra_hours)->toBe(4)
        ->and($record->scheduled_hours)->toBe(3)
        ->and($record->total_hours)->toBe(7);
});

it('appends no Hours adjustment — derivation is not testimony', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH]);

    expect(HoursAdjustment::count())->toBe(0);
});

it('writes at the Group that owns the Schedule, never at its parent', function () {
    $parent = recalcGroup();
    $child = recalcGroup();
    $child->update(['parent_id' => $parent->id]);
    $scheduler = recalcOfficer($child);
    $worker = Member::factory()->create();
    recalcSignUp($child, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($scheduler)->post(route('hours.recalculate', $child), ['year_month' => RECALC_MONTH]);

    $record = HoursRecord::where('member_id', $worker->id)->sole();
    expect($record->group_id)->toBe($child->id);
});

it('refuses a month in a prior fiscal year and writes nothing', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    // A March 2026 Shift belongs to fiscal year 2026 — closed, since the frozen now is in 2027.
    recalcSignUp($group, $worker, '2026-03-05 13:00', 3);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => '202603'])
        ->assertSessionHasErrors('year_month');

    expect(HoursRecord::count())->toBe(0);
});

it('lets a Chair run it (Chair-implication)', function () {
    $group = recalcGroup();
    $chair = recalcOfficer($group, Role::Chair);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($chair)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertSessionHasNoErrors();

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(3);
});

it('forbids an ordinary Member from running it and shows them no control', function () {
    $group = recalcGroup();
    // A member of the Group carrying no role at all — the ordinary case.
    $ordinary = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $ordinary->id]);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-05 13:00', 3);

    $this->actingAs($ordinary)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertForbidden();

    expect(HoursRecord::count())->toBe(0);

    $this->actingAs($ordinary)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn ($page) => $page->where('can.recalculateHours', false));
});

it('refuses on a Group that runs no scheduling and shows no control', function () {
    $group = Group::factory()->publicListing()->create(['has_scheduling' => false]);
    $chair = recalcOfficer($group, Role::Chair);

    $this->actingAs($chair)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertForbidden();

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn ($page) => $page->where('can.recalculateHours', false));
});

it('shows a Scheduler the recalculate control on a scheduling Group', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);

    $this->actingAs($scheduler)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn ($page) => $page->where('can.recalculateHours', true));
});

/*
 * Owner-credit (ADR-0023 §4, #443): hours belong to the Group that owns the Shift. The
 * credit is resolved through the Schedule and joins no roster — no membership test, no
 * audience test. These pin that so a later refactor cannot quietly add a roster join and
 * still pass CI.
 */

it('credits a Member with no Group standing at all under the Group that owns the open Shift', function () {
    $groupA = recalcGroup();
    $elsewhere = recalcGroup();             // an uninvolved Group, to prove the credit does not spill
    $scheduler = recalcOfficer($groupA);
    $helper = Member::factory()->create();  // belongs to no Group — every roster is empty
    recalcSignUp($groupA, $helper, '2026-08-05 13:00', 3, ShiftAudience::Open);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $groupA), ['year_month' => RECALC_MONTH])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // The credit landed with no roster to join — the membership test the code must not have.
    expect(GroupMember::where('member_id', $helper->id)->count())->toBe(0);
    $record = HoursRecord::where('member_id', $helper->id)->sole();
    expect($record->group_id)->toBe($groupA->id)
        ->and($record->scheduled_hours)->toBe(3);
    // Nothing under any other Group from that recalculation.
    expect(HoursRecord::where('group_id', $elsewhere->id)->count())->toBe(0);
});

it('credits the owning Group A when a Member of Group B takes A\'s open Shift, and B\'s recalculation writes them nothing', function () {
    $groupA = recalcGroup();
    $groupB = recalcGroup();
    $schedulerA = recalcOfficer($groupA);
    $schedulerB = recalcOfficer($groupB);
    $taker = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $groupB->id, 'member_id' => $taker->id]);
    recalcSignUp($groupA, $taker, '2026-08-05 13:00', 3, ShiftAudience::Open);

    $this->actingAs($schedulerA)->post(route('hours.recalculate', $groupA), ['year_month' => RECALC_MONTH]);
    $this->actingAs($schedulerB)->post(route('hours.recalculate', $groupB), ['year_month' => RECALC_MONTH]);

    // Exactly one record, filed under A — never the taker's own Group B, never both.
    $record = HoursRecord::where('member_id', $taker->id)->sole();
    expect($record->group_id)->toBe($groupA->id)
        ->and($record->scheduled_hours)->toBe(3);
    expect(HoursRecord::where('group_id', $groupB->id)->count())->toBe(0);
});

/*
 * Month bucketing (ADR-0023 §4, #443): a Shift falls in the month of its ends_at. It
 * matters only for the one Shift that crosses midnight into a new month; every Shift the
 * DMV actually schedules sits wholly inside a day.
 */

it('buckets a Shift by its ends_at — one starting 31 March and ending 1 April lands in April', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    // Starts 2026-03-31 23:00, ends 2026-04-01 01:00 — crosses midnight into April.
    recalcSignUp($group, $worker, '2026-04-01 01:00', 2);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => '202604'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(2);
});

it('buckets a Shift wholly inside one month into that month, unchanged', function () {
    $group = recalcGroup();
    $scheduler = recalcOfficer($group);
    $worker = Member::factory()->create();
    recalcSignUp($group, $worker, '2026-08-10 15:00', 4);   // starts and ends 10 August

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => RECALC_MONTH])
        ->assertRedirect();

    expect(HoursRecord::where('member_id', $worker->id)->sole()->scheduled_hours)->toBe(4);
});
