<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Scheduling tab — the Schedule read surface (#353, PRD #352, ADR-0021 §1).
 * Asserted at the Inertia prop seam (prior art: GroupMeetingsTest). The section is
 * org-open, present only when the Group runs scheduling. A draft is admin-only; a
 * published Schedule follows the Group's listing visibility. Navigation branches on
 * the count of current published Schedules — exactly one opens directly, else a list.
 */

/**
 * Make a member of the given Group (Full standing, no role), optionally carrying a role.
 */
function schedulingMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/**
 * A Group that runs scheduling and is listed org-wide (so a non-member can read its
 * published Schedules).
 */
function schedulingGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

// --- The section renders whenever scheduling is on --------------------------

it('renders the Scheduling tab with an empty state when the Group has no Schedules', function () {
    $group = schedulingGroup();

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'scheduling')
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', []));
});

it('404s the Scheduling section on a Group that does not run scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertNotFound();
});

it('leaves the scheduling prop empty on the Overview section', function () {
    $group = schedulingGroup();
    Schedule::factory()->create(['group_id' => $group->id]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling', ['schedules' => [], 'open' => null, 'roster' => []]));
});

// --- Navigation: list vs open-directly --------------------------------------

it('opens the single current published Schedule directly', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.name', 'August 2026')
            ->where('scheduling.open.id', $schedule->id)
            ->where('scheduling.schedules', []));
});

it('shows the list when two or more current published Schedules exist', function () {
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'September 2026', 'starts_on' => now()->addMonth()->startOfMonth(), 'ends_on' => now()->addMonth()->endOfMonth()]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('August 2026')
                && $s->pluck('name')->contains('September 2026')));
});

it('shows the list anyway when the reader asks for all schedules', function () {
    // The escape from an auto-opened Schedule: `?all=1` opts out of the open-directly
    // branch, so the one current published Schedule and everything past it are listed
    // rather than one of them being opened on top of an unreachable list.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);
    Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'name' => 'Last spring',
        'starts_on' => now()->subMonths(2)->startOfMonth(),
        'ends_on' => now()->subMonths(2)->endOfMonth(),
    ]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling', 'all' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('August 2026')
                && $s->pluck('name')->contains('Last spring')));
});

it('reaches a draft through the list when a published Schedule would otherwise open directly', function () {
    // A Scheduler's draft never changes where a Member lands, which left the draft
    // itself unreachable without knowing its id. Asking for the list gets there.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Published August']);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Draft September']);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling', 'all' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('Draft September')));
});

it('still opens an addressed Schedule when the permalink carries the all flag', function () {
    // `all=1` opts out of open-*directly*; it never overrides an explicitly addressed
    // Schedule, so a link that picked up the flag still lands where it points.
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]).'?all=1')
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.id', $schedule->id));
});

it('does not let a draft change where a Member lands', function () {
    // One current published + a draft: still exactly one *published* current, so it
    // opens directly, and the draft is nowhere in the ordinary Member's payload.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Published August']);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Draft September']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.name', 'Published August')
            ->where('scheduling.schedules', []));
});

// --- Draft audience ---------------------------------------------------------

it('hides a draft from an ordinary Member but shows it to a Scheduler in the list', function () {
    // Two published (so the list branch is taken) plus a draft.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Pub A']);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Pub B', 'starts_on' => now()->addMonth()->startOfMonth(), 'ends_on' => now()->addMonth()->endOfMonth()]);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Secret draft']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.schedules', fn (Collection $s) => ! $s->pluck('name')->contains('Secret draft')));

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('Secret draft')));
});

it('shows a draft to the super-tier without membership', function () {
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Pub A']);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Pub B', 'starts_on' => now()->addMonth()->startOfMonth(), 'ends_on' => now()->addMonth()->endOfMonth()]);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Secret draft']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('Secret draft')));
});

// --- Permalink: addressing a Schedule by id ---------------------------------

it('opens a specific Schedule by id via the permalink', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->past()->create(['group_id' => $group->id, 'name' => 'Last spring']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'scheduling')
            ->where('scheduling.open.id', $schedule->id)
            ->where('scheduling.open.name', 'Last spring'));
});

it('404s a draft permalink for an ordinary Member but opens it for a Scheduler', function () {
    $group = schedulingGroup();
    $draft = Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Work in progress']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $draft->id]))
        ->assertNotFound();

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $draft->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.name', 'Work in progress'));
});

it('404s a permalink whose Schedule belongs to a different Group', function () {
    $group = schedulingGroup();
    $other = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $other->id]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertNotFound();
});

// --- Published-Schedule listing audience ------------------------------------

it('lets a non-member read a published Schedule on a Public Group', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Open month']);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.name', 'Open month'));
});

it('404s a Private Group Schedule for a non-member', function () {
    $group = Group::factory()->program()->privateListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertNotFound();
});

it('lets a member read a Private Group published Schedule', function () {
    $group = Group::factory()->program()->privateListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Members month']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.name', 'Members month'));
});

// --- Ordering: current & upcoming first, then past most-recent-first --------

it('orders the list current-first then past most-recently-ended-first', function () {
    $group = schedulingGroup();
    // Two published current would open-directly; three keeps the list branch and lets
    // ordering be asserted. Created out of order so entry order cannot pass by accident.
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Older past', 'starts_on' => now()->subMonths(4)->startOfMonth(), 'ends_on' => now()->subMonths(4)->endOfMonth()]);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Current', 'starts_on' => now()->startOfMonth(), 'ends_on' => now()->endOfMonth()]);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Recent past', 'starts_on' => now()->subMonth()->startOfMonth(), 'ends_on' => now()->subMonth()->endOfMonth()]);
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Upcoming', 'starts_on' => now()->addMonth()->startOfMonth(), 'ends_on' => now()->addMonth()->endOfMonth()]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->all() === [
                'Current',
                'Upcoming',
                'Recent past',
                'Older past',
            ]));
});

// --- French route segment ---------------------------------------------------

it('resolves the French Schedule permalink twin /fr/groupes/{group}/horaire/{schedule}', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Aout 2026']);
    $this->actingAs(schedulingMemberOf($group));

    $this->withLocaleRoutes('fr', function () use ($group, $schedule) {
        $this->get("/fr/groupes/{$group->slug}/horaire/{$schedule->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('groups/Show')
                ->where('locale', 'fr')
                ->where('scheduling.open.name', 'Aout 2026'));
    });
});

// --- The Agenda read surface: a Schedule's Shifts (#355, ADR-0021 §2) --------

it('lists an opened Schedule with an empty shifts array when it holds no Shifts', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts', []));
});

it("exposes each Shift's date, times, capacity, taken-count and kind on the open Schedule", function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights tour']);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind->id,
        'capacity' => 3,
        'starts_at' => '2026-08-05 14:00:00',
        'ends_at' => '2026-08-05 17:00:00',
    ]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.open.shifts', 1)
            ->where('scheduling.open.shifts.0.id', $shift->id)
            ->where('scheduling.open.shifts.0.capacity', 3)
            // No Sign-ups exist yet (#357), so every Shift reads as empty.
            ->where('scheduling.open.shifts.0.taken', 0)
            ->where('scheduling.open.shifts.0.kind', 'Highlights tour')
            ->where('scheduling.open.shifts.0.starts_at', fn (string $iso) => str_starts_with($iso, '2026-08-05'))
            ->where('scheduling.open.shifts.0.ends_at', fn (string $iso) => str_starts_with($iso, '2026-08-05')));
});

it('reads a kind-less Shift with a null kind', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    Shift::factory()->create(['schedule_id' => $schedule->id, 'shift_kind_id' => null]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.kind', null));
});

it('orders Shifts by start time within the Schedule', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    // Created out of order so the ordering cannot pass by insertion order.
    $afternoon = Shift::factory()->create(['schedule_id' => $schedule->id, 'starts_at' => '2026-08-05 14:00:00', 'ends_at' => '2026-08-05 16:00:00']);
    $morning = Shift::factory()->create(['schedule_id' => $schedule->id, 'starts_at' => '2026-08-05 09:00:00', 'ends_at' => '2026-08-05 11:00:00']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts', fn (Collection $shifts) => $shifts->pluck('id')->all() === [$morning->id, $afternoon->id]));
});
