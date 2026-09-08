<?php

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The outstanding-shifts panel on the Group's Scheduling tab (#449, PRD #443, ADR-0023 §5) —
 * "my Sign-ups on this Group": upcoming Shifts, plus any past Shift inside a 28-day window that
 * still has a null visitor count. It is date-ranged, so it crosses Schedules, and it is the
 * viewer's own seats and no one else's. Absent (not empty) when there is nothing to show, and
 * absent entirely on a Group that collects nothing. The panel reads from `scheduling.mine`.
 *
 * A fixed clock so the 28-day window is exact. `ends_at` is a stored UTC instant, so every time
 * here is UTC, matching the rest of the scheduling suite. Prior art: SignUpVisitorCountTest.
 */
function outstandingNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 12:00');
}

/** A Group that runs scheduling, is listed org-wide, and collects a per-shift visitor count. */
function panelGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

/** A Member of the given Group in good standing. */
function panelMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    return $member;
}

/** Open the Group's bare Scheduling section (the list view) as the given Member. */
function viewScheduling(Member $member, Group $group)
{
    return test()->actingAs($member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']));
}

it('lists an upcoming Sign-up in the panel', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-09-30',
    ]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-20 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-20 13:00'),
    ]);
    $member = panelMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine', fn ($mine) => count($mine) === 1 && $mine[0]['signup_id'] === $signUp->id));
});

it('lists a past Shift with a null visitor count', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-05 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-05 13:00'),
    ]);
    $member = panelMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]); // null count

    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine', fn ($mine) => count($mine) === 1));
});

it('excludes a past Shift whose visitor count is a recorded zero', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-05 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-05 13:00'),
    ]);
    $member = panelMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id, 'visitor_count' => 0]);

    // Owes nothing, holds no upcoming seat — no panel at all.
    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

it('excludes a past Shift older than the 28-day window', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    // Ended 29 days before now — outside the window.
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-08-12 10:00'),
        'ends_at' => outstandingNow()->subDays(29),
    ]);
    $member = panelMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]); // null count

    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

it('includes a 27-day-old outstanding Shift on a previous Schedule, crossing Schedules', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();

    // The current Schedule the viewer would be reading, holding no relevant Shift for them.
    $current = Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-09-30',
    ]);

    // A previous Schedule — a different page — carrying the outstanding Shift.
    $previous = Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);
    $oldShift = Shift::factory()->create([
        'schedule_id' => $previous->id,
        'starts_at' => outstandingNow()->subDays(27)->setTime(10, 0),
        'ends_at' => outstandingNow()->subDays(27)->setTime(13, 0),
    ]);
    $member = panelMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $oldShift->id, 'member_id' => $member->id]); // null count

    // Open the current Schedule by permalink; the panel still reaches the previous one's Shift.
    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $current->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine', fn ($mine) => count($mine) === 1 && $mine[0]['signup_id'] === $signUp->id));
});

it('shows only the viewer’s own seats, never another Member’s', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'capacity' => 2,
        'starts_at' => CarbonImmutable::parse('2026-09-05 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-05 13:00'),
    ]);
    $viewer = panelMemberOf($group);
    $other = panelMemberOf($group);
    $viewerSeat = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $viewer->id]);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $other->id]);

    viewScheduling($viewer, $group)
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine', fn ($mine) => count($mine) === 1 && $mine[0]['signup_id'] === $viewerSeat->id));
});

it('gives a reader with no Sign-ups on the Group no panel', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-20 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-20 13:00'),
    ]);

    // A member of the Group who never signed up for anything.
    viewScheduling(panelMemberOf($group), $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

it('renders no panel on a Group that collects nothing', function () {
    $this->travelTo(outstandingNow());

    $group = Group::factory()->program()->publicListing()->create(); // collects_visitor_count false
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-05 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-05 13:00'),
    ]);
    $member = panelMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]); // outstanding, but no collecting

    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

it('drops a Shift from the outstanding list once a number is filed from the panel', function () {
    $this->travelTo(outstandingNow());

    $group = panelGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-05 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-05 13:00'),
    ]);
    $member = panelMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    // Before: outstanding, in the panel.
    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', fn ($mine) => count($mine) === 1));

    // File the number straight from the panel — the same PATCH seam the Agenda uses.
    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 8])
        ->assertRedirect();

    // After: recorded, so it leaves the outstanding list.
    viewScheduling($member, $group)
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

it('exposes the outstanding window as a named constant, not a call-site literal', function () {
    expect(SignUp::OUTSTANDING_WINDOW_DAYS)->toBe(28);
});
