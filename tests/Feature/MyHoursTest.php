<?php

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\OrgTime;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The My Hours destination (#409, PRD #406, ADR-0022 §8). The one place not inside any
 * Group where a Member answers "how many hours have I put in this year?" — every Group
 * they have hours in, broken out by month across a fiscal year, with scheduled and extra
 * shown apart and a year-to-date total. Asserted at the Inertia prop seam.
 *
 * Frozen inside Fiscal 2026 (runs 2025-04 through 2026-03) so the default window and the
 * month boundaries are fixed regardless of when the suite runs.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
});

/** A record for one Member on one Group in one month, at the no-meeting grain. */
function myHoursRecord(Member $member, Group $group, string $yearMonth, int $scheduled, int $extra): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

it('renders the My Hours page with the current fiscal year by default', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('hours'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyHours')
            ->where('fiscalYear', OrgTime::currentFiscalYear()));
});

it('lists every Group the Member has hours in, including one they do not belong to', function () {
    $member = Member::factory()->create();
    $belongs = Group::factory()->create(['name' => 'Docents']);
    $helped = Group::factory()->create(['name' => 'ROMWalks']);
    myHoursRecord($member, $belongs, '202505', 3, 1);
    myHoursRecord($member, $helped, '202506', 0, 4);

    $this->actingAs($member)
        ->get(route('hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 2)
            ->where('groups.0.name', 'Docents')
            ->where('groups.1.name', 'ROMWalks'));
});

it('shows no other Member\'s hours', function () {
    $member = Member::factory()->create();
    $other = Member::factory()->create();
    $group = Group::factory()->create();
    myHoursRecord($other, $group, '202505', 5, 5);

    $this->actingAs($member)
        ->get(route('hours'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', []));
});

it('breaks a Group out by twelve months in April-to-March order with scheduled, extra, and total apart', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    myHoursRecord($member, $group, '202504', 2, 1);
    myHoursRecord($member, $group, '202603', 3, 4);

    $this->actingAs($member)
        ->get(route('hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 12)
            ->where('months.0.year_month', '202504')
            ->where('months.11.year_month', '202603')
            ->has('groups.0.months', 12)
            ->where('groups.0.months.0.scheduled_hours', 2)
            ->where('groups.0.months.0.extra_hours', 1)
            ->where('groups.0.months.0.total_hours', 3)
            ->where('groups.0.months.11.scheduled_hours', 3)
            ->where('groups.0.months.11.extra_hours', 4)
            ->where('groups.0.months.11.total_hours', 7)
            // A month with nothing on file reads zero, not a gap.
            ->where('groups.0.months.5.total_hours', 0));
});

it('shows a year-to-date total equal to the sum of the twelve months', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    myHoursRecord($member, $group, '202504', 2, 1);
    myHoursRecord($member, $group, '202510', 5, 0);
    myHoursRecord($member, $group, '202603', 3, 4);

    $this->actingAs($member)
        ->get(route('hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.ytd.scheduled_hours', 10)
            ->where('groups.0.ytd.extra_hours', 5)
            ->where('groups.0.ytd.total_hours', 15));
});

it('moves the window to a past fiscal year the Member picks', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    // Two years back: Fiscal 2024 runs 2023-04 through 2024-03.
    myHoursRecord($member, $group, '202309', 6, 0);

    $this->actingAs($member)
        ->get(route('hours', ['fy' => 2024]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYear', 2024)
            ->where('months.0.year_month', '202304')
            ->has('groups', 1)
            ->where('groups.0.ytd.total_hours', 6));
});

it('files a March record in the fiscal year ending that March and an April record in the next', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    myHoursRecord($member, $group, '202603', 3, 0); // last month of Fiscal 2026
    myHoursRecord($member, $group, '202604', 4, 0); // first month of Fiscal 2027

    // Fiscal 2026 sees only the March record.
    $this->actingAs($member)
        ->get(route('hours', ['fy' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.ytd.scheduled_hours', 3));

    // Fiscal 2027 sees only the April record.
    $this->actingAs($member)
        ->get(route('hours', ['fy' => 2027]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.ytd.scheduled_hours', 4));
});

it('offers the fiscal years the Member has hours in, newest first, alongside the current one', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create();
    myHoursRecord($member, $group, '202309', 1, 0); // Fiscal 2024
    myHoursRecord($member, $group, '202505', 1, 0); // Fiscal 2026

    $this->actingAs($member)
        ->get(route('hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYears', [2026, 2024]));
});

it('gives a Member with no hours an empty page, not a broken one', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('hours'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups', [])
            // Still offers the current fiscal year to look at.
            ->where('fiscalYears', [OrgTime::currentFiscalYear()]));
});

it('resolves the destination under the French /fr/ path', function () {
    $member = Member::factory()->create();

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->actingAs($member)
            ->get('/fr/heures')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('MyHours')->where('locale', 'fr'));
    });
});
