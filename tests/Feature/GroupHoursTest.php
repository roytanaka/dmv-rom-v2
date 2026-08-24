<?php

use App\Enums\Category;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\OrgTime;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Hours tab read surface (#408, PRD #406, ADR-0022 §2, §4). Asserted at the Inertia
 * prop seam (prior art: GroupSchedulingTest). The tab shows the viewer their own records
 * and the two-month entry state — and nobody else's hours, here or anywhere.
 */

/** A Group listed org-wide. */
function hoursReadGroup(): Group
{
    return Group::factory()->publicListing()->create();
}

// --- The viewer's own records -----------------------------------------------

it('lists the viewer their own records for the Group', function () {
    $group = hoursReadGroup();
    $member = Member::factory()->create();
    HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => '202606',
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => 4,
        'extra_hours' => 2,
        'total_hours' => 6,
    ]);

    $this->actingAs($member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'hours')
            ->has('hours.records', 1)
            ->where('hours.records.0.year_month', '202606')
            ->where('hours.records.0.scheduled_hours', 4)
            ->where('hours.records.0.extra_hours', 2)
            ->where('hours.records.0.total_hours', 6));
});

it('shows no other Member\'s records through the tab', function () {
    $group = hoursReadGroup();
    $viewer = Member::factory()->create();
    $other = Member::factory()->create();
    HoursRecord::factory()->create([
        'member_id' => $other->id,
        'group_id' => $group->id,
        'meeting_id' => HoursRecord::NO_MEETING,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page->where('hours.records', []));
});

it('renders the Hours tab with no records as an empty list, not a null', function () {
    $group = hoursReadGroup();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page->where('hours.records', []));
});

// --- The two-month entry state ----------------------------------------------

it('offers exactly the current and previous month for entry', function () {
    $group = hoursReadGroup();
    [$current, $previous] = OrgTime::entryMonths();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('hours.months', 2)
            ->where('hours.months.0.year_month', $current)
            ->where('hours.months.1.year_month', $previous));
});

it('shows each entry month the hours already on file and when they were last touched', function () {
    $group = hoursReadGroup();
    $member = Member::factory()->create();
    [$current] = OrgTime::entryMonths();
    HoursRecord::enterExtra($member, $group, $current, 7, $member);

    $this->actingAs($member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hours.months.0.year_month', $current)
            ->where('hours.months.0.extra_hours', 7)
            ->where('hours.months.0.updated_at', fn (?string $iso) => $iso !== null)
            // The previous month has nothing on file yet.
            ->where('hours.months.1.extra_hours', 0)
            ->where('hours.months.1.updated_at', null));
});

// --- The entry `can` hint ---------------------------------------------------

it('hints entry on for a participating Member and off for a departed one', function () {
    $group = hoursReadGroup();

    $this->actingAs(Member::factory()->create(['category' => Category::Active]))
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page->where('can.enterHours', true));

    $this->actingAs(Member::factory()->create(['category' => Category::Resigned]))
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page->where('can.enterHours', false));
});

// --- A sub-Group's records are its own --------------------------------------

it('shows a sub-Group only its own records, not its parent\'s', function () {
    $parent = hoursReadGroup();
    $child = Group::factory()->publicListing()->create(['parent_id' => $parent->id]);
    $member = Member::factory()->create();
    HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $parent->id,
        'meeting_id' => HoursRecord::NO_MEETING,
    ]);

    $this->actingAs($member)
        ->get(route('groups.show', ['group' => $child, 'section' => 'hours']))
        ->assertInertia(fn (Assert $page) => $page->where('hours.records', []));
});

// --- Empty on non-Hours sections --------------------------------------------

it('leaves the hours prop empty on the Overview section', function () {
    $group = hoursReadGroup();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('hours', ['records' => [], 'months' => []]));
});
