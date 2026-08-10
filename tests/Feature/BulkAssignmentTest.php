<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\SignUpCancelled;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Support\Facades\Mail;

/*
 * Bulk-place and bulk-remove a Member's Sign-ups (#363, PRD #352, ADR-0021 §5) — the
 * Scheduler's labour-saver that retires Reception's fortnight. A run loops a Member over the
 * Shifts a filter names (days of week + wall-clock time, a date range, an interval of weekly
 * or biweekly from a chosen start date) and writes an ordinary Sign-up on each — with a
 * symmetric bulk-remove on the same filter.
 *
 * This adds no schema: the interval lives in the form, never in a row (no recurrence column,
 * no phase anchor). A run is **N single writes plus a report** — skip-and-report, never
 * all-or-nothing: a full Shift or a seat the Member already holds is skipped and named, and
 * the rest are still written. It is a Scheduler action bound by both sign-up floors (checked
 * once — the Member and Group are constant across rows) and by capacity and the one-seat
 * rule per row. Prior art: OfficerAssignmentTest (single placement), ShiftTest (bulk Shifts).
 */

/** A Group that runs scheduling and is listed org-wide. */
function bulkGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/** Make a Member of the given Group with a chosen standing. */
function bulkMemberOf(
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

/** The Group's Scheduler — the actor who bulk-places and bulk-removes. */
function bulkSchedulerOf(Group $group): Member
{
    return bulkMemberOf($group, MembershipStatus::Full, Category::Active, Role::Scheduler);
}

/** A published Schedule spanning the whole of August 2026 on a scheduling-Group. */
function bulkSchedule(?Group $group = null): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => ($group ?? bulkGroup())->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);
}

/** A 10:00–13:00 Shift on the given date, on the given Schedule. */
function bulkShiftOn(Schedule $schedule, string $date, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => "{$date} 10:00:00",
        'ends_at' => "{$date} 13:00:00",
        ...$overrides,
    ]);
}

/** The five Mondays of August 2026 as 10:00–13:00 Shifts on the Schedule. */
function bulkAugustMondays(Schedule $schedule): void
{
    foreach (['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31'] as $date) {
        bulkShiftOn($schedule, $date);
    }
}

/** A valid bulk-place filter: every Monday of August 2026, 10:00–13:00, weekly. */
function bulkPayload(array $overrides = []): array
{
    return [
        'starts_time' => '10:00',
        'ends_time' => '13:00',
        'days_of_week' => [1],
        'from_date' => '2026-08-01',
        'to_date' => '2026-08-31',
        'interval' => 'weekly',
        'anchor_date' => '2026-08-03',
        ...$overrides,
    ];
}

// --- Bulk-place — weekly ------------------------------------------------------

it('places a Member on every matching Shift in the range and reports the count', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('assignmentsBulk', fn ($report) => $report['created'] === 5 && $report['skipped'] === []);

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(5);
});

// --- Bulk-place — biweekly ----------------------------------------------------

it('places on alternating weeks only when the interval is biweekly', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    // Anchored on the first Monday: weeks 0, 2, 4 are on (Aug 3, 17, 31); 1, 3 are off.
    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload([
            'member_id' => $regular->id,
            'interval' => 'biweekly',
            'anchor_date' => '2026-08-03',
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('assignmentsBulk', fn ($report) => $report['created'] === 3 && $report['skipped'] === []);

    $placed = SignUp::where('member_id', $regular->id)->with('shift')->get()
        ->map(fn (SignUp $signUp) => $signUp->shift->starts_at->toDateString())
        ->sort()->values()->all();

    expect($placed)->toBe(['2026-08-03', '2026-08-17', '2026-08-31']);
});

// --- Bulk-place — skip-and-report, never all-or-nothing -----------------------

it('writes the rest and reports the full Shift when one is at capacity', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    // The second Monday is a capacity-1 Shift already taken by someone else — full, so the
    // run skips it and names it, and still writes the other four.
    $full = Shift::where('starts_at', '2026-08-10 10:00:00')->sole();
    SignUp::factory()->create(['shift_id' => $full->id, 'member_id' => bulkMemberOf($schedule->group)->id]);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('assignmentsBulk', function ($report) use ($full) {
            return $report['created'] === 4
                && count($report['skipped']) === 1
                && $report['skipped'][0]['shift_id'] === $full->id
                && $report['skipped'][0]['reason'] === 'group.scheduling_panel.bulk.skipped_full';
        });

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(4)
        ->and($full->signUps()->where('member_id', $regular->id)->exists())->toBeFalse();
});

it('writes the rest and reports a Shift the Member already holds a seat on', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    // The Member already holds the third Monday — the one-seat rule skips it and names it.
    $held = Shift::where('starts_at', '2026-08-17 10:00:00')->sole();
    SignUp::factory()->create(['shift_id' => $held->id, 'member_id' => $regular->id]);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('assignmentsBulk', function ($report) use ($held) {
            return $report['created'] === 4
                && count($report['skipped']) === 1
                && $report['skipped'][0]['shift_id'] === $held->id
                && $report['skipped'][0]['reason'] === 'group.scheduling_panel.bulk.skipped_already_signed_up';
        });

    // Four new seats plus the one pre-existing — no duplicate on the held Shift.
    expect(SignUp::where('member_id', $regular->id)->count())->toBe(5)
        ->and($held->signUps()->where('member_id', $regular->id)->count())->toBe(1);
});

// --- Bulk-place — the floors and the schedule-admin gate ----------------------

it('forbids a run whose Member is barred by the per-Group floor — nothing is written', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    // Resigned in this Group: the per-Group floor bars placement on every row, so the whole
    // run is refused up front rather than writing a partial batch.
    $resigned = bulkMemberOf($schedule->group, MembershipStatus::Resigned);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $resigned->id]))
        ->assertForbidden();

    expect(SignUp::where('member_id', $resigned->id)->count())->toBe(0);
});

it('forbids a run whose Member is on DMV-wide leave — the DMV-wide floor binds', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    // Full per-Group standing but LOA org-wide: the DMV-wide floor still bars the run.
    $onLeave = bulkMemberOf($schedule->group, MembershipStatus::Full, Category::Loa);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $onLeave->id]))
        ->assertForbidden();

    expect(SignUp::where('member_id', $onLeave->id)->count())->toBe(0);
});

it('forbids an ordinary Member from bulk-placing — placement is not a self-service verb', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $ordinary = bulkMemberOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($ordinary)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertForbidden();

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(0);
});

it('forbids a Scheduler of another Group from bulk-placing — authority never leaks', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $foreignScheduler = bulkSchedulerOf(bulkGroup());
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($foreignScheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertForbidden();

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(0);
});

// --- Bulk-remove — the symmetric undo on the same filter ----------------------

it('removes the Member’s Sign-ups on every matching Shift and reports the count', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($scheduler)->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]));
    expect(SignUp::where('member_id', $regular->id)->count())->toBe(5);

    $this->actingAs($scheduler)
        ->delete(route('assignments.bulk-destroy', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('assignmentsBulk', fn ($report) => $report['removed'] === 5);

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(0);
});

it('bulk-remove leaves other Members’ seats and non-matching Shifts untouched', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);
    $other = bulkMemberOf($schedule->group);

    // The regular takes every Monday plus a lone Tuesday the filter must not touch; another
    // Member also holds the first Monday — their seat must survive.
    $this->actingAs($scheduler)->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]));
    $tuesday = bulkShiftOn($schedule, '2026-08-04');
    SignUp::factory()->create(['shift_id' => $tuesday->id, 'member_id' => $regular->id]);
    $firstMonday = Shift::where('starts_at', '2026-08-03 10:00:00')->sole();
    SignUp::factory()->create(['shift_id' => $firstMonday->id, 'member_id' => $other->id]);

    $this->actingAs($scheduler)
        ->delete(route('assignments.bulk-destroy', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHas('assignmentsBulk', fn ($report) => $report['removed'] === 5);

    // The regular keeps only the Tuesday seat; the other Member keeps their Monday seat.
    expect(SignUp::where('member_id', $regular->id)->pluck('shift_id')->all())->toBe([$tuesday->id])
        ->and($firstMonday->signUps()->where('member_id', $other->id)->exists())->toBeTrue();
});

it('bulk-remove clears a Member the floors would now bar — removal answers only to the admin gate', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($scheduler)->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]));

    // The regular stops coming and their membership is set to resigned — the very case the
    // floors would bar a placement. Removal must still clear their stranded seats.
    GroupMember::where(['group_id' => $schedule->group_id, 'member_id' => $regular->id])
        ->update(['status' => MembershipStatus::Resigned]);

    $this->actingAs($scheduler)
        ->delete(route('assignments.bulk-destroy', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertSessionHas('assignmentsBulk', fn ($report) => $report['removed'] === 5);

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(0);
});

it('forbids an ordinary Member from bulk-removing Sign-ups', function () {
    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $ordinary = bulkMemberOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);
    $firstMonday = Shift::where('starts_at', '2026-08-03 10:00:00')->sole();
    SignUp::factory()->create(['shift_id' => $firstMonday->id, 'member_id' => $regular->id]);

    $this->actingAs($ordinary)
        ->delete(route('assignments.bulk-destroy', $schedule), bulkPayload(['member_id' => $regular->id]))
        ->assertForbidden();

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(1);
});

// --- The deliberate silence: neither bulk verb sends an email -----------------

it('sends no email when a Scheduler bulk-places or bulk-removes', function () {
    Mail::fake();

    $schedule = bulkSchedule();
    bulkAugustMondays($schedule);
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($scheduler)->post(route('assignments.bulk-store', $schedule), bulkPayload(['member_id' => $regular->id]));
    $this->actingAs($scheduler)->delete(route('assignments.bulk-destroy', $schedule), bulkPayload(['member_id' => $regular->id]));

    Mail::assertNotSent(SignUpCancelled::class);
});

// --- Validation: the interval is a required, closed choice --------------------

it('rejects a run without a valid interval', function () {
    $schedule = bulkSchedule();
    $scheduler = bulkSchedulerOf($schedule->group);
    $regular = bulkMemberOf($schedule->group);

    $this->actingAs($scheduler)
        ->post(route('assignments.bulk-store', $schedule), bulkPayload([
            'member_id' => $regular->id,
            'interval' => 'monthly',
        ]))
        ->assertSessionHasErrors('interval');

    expect(SignUp::where('member_id', $regular->id)->count())->toBe(0);
});
