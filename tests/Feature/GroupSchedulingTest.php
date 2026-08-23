<?php

use App\Enums\Role;
use App\Enums\ShiftAudience;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Scheduling tab — the Schedule read surface (#353, PRD #352, ADR-0021 §1).
 * Asserted at the Inertia prop seam (prior art: GroupMeetingsTest). The section is
 * org-open, present only when the Group runs scheduling. A draft is admin-only; a
 * published Schedule follows the Group's listing visibility. The section always shows
 * the list; only a permalink opens one Schedule.
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
        ->assertInertia(fn (Assert $page) => $page->where('scheduling', ['schedules' => [], 'open' => null, 'roster' => [], 'shift_kinds' => []]));
});

// --- Navigation: always the list, whatever the Group holds -------------------

it('lists a single current published Schedule rather than opening it', function () {
    // The section is an index, like every other section tab. One current published
    // Schedule used to open directly; it is now simply the only row in the list.
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->has('scheduling.schedules', 1)
            ->where('scheduling.schedules.0.name', 'August 2026')
            ->where('scheduling.schedules.0.id', $schedule->id));
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

it('lists a past Schedule beside the current one', function () {
    // Nothing is hidden by date, and no Schedule is skipped over on the way in.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'August 2026']);
    Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'name' => 'Last spring',
        'starts_on' => now()->subMonths(2)->startOfMonth(),
        'ends_on' => now()->subMonths(2)->endOfMonth(),
    ]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('August 2026')
                && $s->pluck('name')->contains('Last spring')));
});

it('shows a Scheduler their draft beside a current published Schedule', function () {
    // The case that used to branch: one current published Schedule plus a draft. The
    // draft is in the Scheduler's list, and nothing opens over the top of it.
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Published August']);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Draft September']);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->where('scheduling.schedules', fn (Collection $s) => $s->pluck('name')->contains('Draft September')
                && $s->pluck('name')->contains('Published August')));
});

it('keeps a draft out of an ordinary Member\'s list beside a current published Schedule', function () {
    $group = schedulingGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'Published August']);
    Schedule::factory()->draft()->create(['group_id' => $group->id, 'name' => 'Draft September']);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open', null)
            ->has('scheduling.schedules', 1)
            ->where('scheduling.schedules.0.name', 'Published August'));
});

// --- Draft audience ---------------------------------------------------------

it('hides a draft from an ordinary Member but shows it to a Scheduler in the list', function () {
    // Two published plus a draft: the draft is the only row that differs by viewer.
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
    // Created out of order so entry order cannot pass the assertion by accident.
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

// --- Shift authoring hints and fields (#356 front end, #381) ------------------
//
// The Shift authoring UI reads three additions to the payload: the per-Shift `can.update`
// / `can.delete` hints (mirroring the officer `can.assign`), each Shift's `audience` and
// `shift_kind_id` (so an edit round-trips the discovery filter and pre-selects the kind),
// and the Group's `shift_kinds` for the kind picker. The write seam itself is covered by
// tests/Feature/Authorization/ShiftTest.php; these assert only the read-side seam.

it('gives a schedule admin the per-Shift update and delete hints and the audience / kind fields', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights tour']);
    Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind->id,
        'audience' => ShiftAudience::Open,
    ]);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            // A schedule admin may edit and — at zero Sign-ups — delete this Shift.
            ->where('scheduling.open.shifts.0.can.update', true)
            ->where('scheduling.open.shifts.0.can.delete', true)
            // The audience and the chosen kind's id round-trip so an edit pre-fills both.
            ->where('scheduling.open.shifts.0.audience', 'open')
            ->where('scheduling.open.shifts.0.shift_kind_id', $kind->id));
});

it('withholds the update and delete hints from an ordinary reader', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    Shift::factory()->create(['schedule_id' => $schedule->id]);

    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.can.update', false)
            ->where('scheduling.open.shifts.0.can.delete', false));
});

it('withholds the delete hint once a Shift has Sign-ups, keeping the edit hint', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id, 'capacity' => 2]);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => schedulingMemberOf($group)->id]);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            // Deletion is cancelling and cancelling is never silent — a seated Shift must be
            // emptied first (ADR-0021 §2), so the delete affordance hides while edit stays.
            ->where('scheduling.open.shifts.0.can.update', true)
            ->where('scheduling.open.shifts.0.can.delete', false));
});

it('ships the Group’s shift kinds to a schedule admin on an opened Schedule', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights tour']);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.shift_kinds', 1)
            ->where('scheduling.shift_kinds.0.id', $kind->id)
            ->where('scheduling.shift_kinds.0.name', 'Highlights tour'));
});

it('withholds the shift kinds from an ordinary reader and on the list view', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    ShiftKind::factory()->create(['group_id' => $group->id]);

    // An ordinary reader on the opened Schedule authors nothing, so gets no kinds.
    $this->actingAs(schedulingMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.shift_kinds', []));

    // The list view offers no shift form, so it carries no kinds even for an admin.
    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.shift_kinds', []));
});

// --- Bulk-create / bulk-delete report reaches the page (#362 front end) --------
//
// A bulk run is N single writes plus a report: `ShiftController::bulkStore` / `bulkDestroy`
// flash the counts and every skipped row with `back()->with('shiftsBulk', …)`. For the
// Scheduler to read that report it has to survive the Inertia redirect, so
// `HandleInertiaRequests` shares a `flash` prop and the report rides into the next page's
// props. The write seam itself is covered by tests/Feature/Authorization/ShiftTest.php;
// this asserts only that the report reaches the page.

it('carries the bulk-create report into the page props via the shared flash prop', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);
    $scheduler = schedulingMemberOf($group, Role::Scheduler);

    // Every Monday from August into the first week of September: the five August Mondays
    // are written, and the September Monday (Sept 7) falls outside the range and is skipped.
    $this->actingAs($scheduler)->post(route('shifts.bulk-store', $schedule), [
        'starts_time' => '10:00',
        'ends_time' => '13:00',
        'days_of_week' => [1],
        'from_date' => '2026-08-01',
        'to_date' => '2026-09-07',
    ]);

    // The report rides the redirect into the opened Schedule's props as a flash prop.
    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.shiftsBulk.created', 5)
            ->has('flash.shiftsBulk.skipped', 1)
            ->where('flash.shiftsBulk.skipped.0.reason', 'group.scheduling_panel.bulk.skipped_outside_range'));
});

it('leaves the flash prop empty when no bulk run has happened', function () {
    $group = schedulingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $this->actingAs(schedulingMemberOf($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('flash.shiftsBulk', null));
});
