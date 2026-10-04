<?php

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use App\Support\CommitteeHoursStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * The DMV-wide committee-statistics matrix (#413, PRD #406, ADR-0022 §8) — the arithmetic
 * behind Summary and Detailed Committee Statistics. Given the DMV root Group and a fiscal
 * year it returns, for the root and each direct-child committee, a twelve-month breakdown of
 * shifts / meetings / extra / total over that Group's whole subtree, plus the year-to-date.
 * The root's row is the complete org total: its subtree is everything, so nothing is orphaned.
 *
 * This is the "service class with non-trivial logic" the conventions reserve a unit test for,
 * so it is exercised directly here — rows built by factory, no HTTP. Bucketing runs in PHP
 * against the `year_month` string, so the result does not depend on the engine's date handling.
 */
uses(TestCase::class, RefreshDatabase::class);

/** A record carrying the given scheduled/extra hours, at the no-meeting or a meeting grain. */
function committeeRecord(Group $group, string $yearMonth, int $scheduled = 0, int $extra = 0, int $meetingId = HoursRecord::NO_MEETING): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => $meetingId,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

/** A signed-out Sign-up on a Shift owned by the Group, ending mid-month in the org zone. */
function committeeSignUp(Group $group, string $yearMonth, int $visitors = 0, ?int $extra = null): SignUp
{
    $endsAt = CarbonImmutable::createFromFormat('Ym', $yearMonth, config('app.org_timezone'))
        ->startOfMonth()->addDays(14)->setTime(12, 0);
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => $endsAt->subHours(3),
        'ends_at' => $endsAt,
    ]);

    return SignUp::factory()->create([
        'shift_id' => $shift->id,
        'member_id' => Member::factory()->create()->id,
        'visitor_count' => $visitors,
        'extra_interaction_count' => $extra,
    ]);
}

/** An Hours record carrying extra interactions for the Group in the month. */
function committeeInteractions(Group $group, string $yearMonth, int $count): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => 0,
        'extra_hours' => 0,
        'total_hours' => 0,
        'extra_interactions' => $count,
    ]);
}

it('labels twelve buckets in April-to-March order for the fiscal year', function () {
    $stats = CommitteeHoursStatistics::for(Group::factory()->create(), 2026);

    expect($stats->months)->toHaveCount(12)
        ->and($stats->months[0])->toBe('202504')
        ->and($stats->months[11])->toBe('202603');
});

it('lists one committee row per direct child of the root, ordered by name', function () {
    $root = Group::factory()->create();
    Group::factory()->create(['parent_id' => $root->id, 'name' => 'Zebra Committee']);
    Group::factory()->create(['parent_id' => $root->id, 'name' => 'Alpha Committee']);
    // A grandchild is folded into its parent committee, never its own row.
    $child = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Beta Committee']);
    Group::factory()->create(['parent_id' => $child->id, 'name' => 'Deep Sub-Group']);

    $stats = CommitteeHoursStatistics::for($root, 2026);

    expect(array_column($stats->committees, 'name'))->toBe(['Alpha Committee', 'Beta Committee', 'Zebra Committee']);
});

it('splits a month into shifts, meetings, and extra, and totals them', function () {
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    committeeRecord($committee, '202504', scheduled: 5);                 // shifts
    committeeRecord($committee, '202504', extra: 3, meetingId: 99);      // meeting hours
    committeeRecord($committee, '202504', extra: 2);                     // extra (no meeting)

    $april = CommitteeHoursStatistics::for($root, 2026)->committees[0]['months'][0];

    expect($april)->toMatchArray([
        'year_month' => '202504',
        'shifts' => 5,
        'meetings' => 3,
        'extra' => 2,
        'total' => 10,
    ]);
});

it('rolls a grandchild three levels down into its committee row and the org total', function () {
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    $child = Group::factory()->create(['parent_id' => $committee->id]);
    $grandchild = Group::factory()->create(['parent_id' => $child->id]);

    committeeRecord($committee, '202504', extra: 1);
    committeeRecord($child, '202504', extra: 2);
    committeeRecord($grandchild, '202504', extra: 4);

    $stats = CommitteeHoursStatistics::for($root, 2026);

    // The committee row reaches its whole subtree, three deep.
    expect($stats->committees[0]['months'][0]['extra'])->toBe(7)
        // The org row is the complete total — the whole subtree of the root.
        ->and($stats->org['months'][0]['extra'])->toBe(7);
});

it('makes the org row include hours logged directly against the root, so the total is complete', function () {
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    committeeRecord($root, '202504', extra: 6);      // logged on the DMV itself
    committeeRecord($committee, '202504', extra: 4);

    $stats = CommitteeHoursStatistics::for($root, 2026);

    // The org total includes the root's own hours and every committee's.
    expect($stats->org['months'][0]['extra'])->toBe(10)
        ->and($stats->org['ytd']['extra'])->toBe(10);
});

it('returns twelve zero buckets for a committee with no records, never a short list', function () {
    $root = Group::factory()->create();
    Group::factory()->create(['parent_id' => $root->id]);

    $months = CommitteeHoursStatistics::for($root, 2026)->committees[0]['months'];

    expect($months)->toHaveCount(12)
        ->and(array_column($months, 'total'))->toBe(array_fill(0, 12, 0));
});

it('files a March record in the year ending that March and an April record in the next', function () {
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    committeeRecord($committee, '202603', extra: 3); // last month of Fiscal 2026
    committeeRecord($committee, '202604', extra: 4); // first month of Fiscal 2027

    expect(CommitteeHoursStatistics::for($root, 2026)->committees[0]['ytd']['extra'])->toBe(3)
        ->and(CommitteeHoursStatistics::for($root, 2027)->committees[0]['ytd']['extra'])->toBe(4);
});

it('makes year-to-date the sum of the twelve buckets for each grain', function () {
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    committeeRecord($committee, '202504', scheduled: 2);
    committeeRecord($committee, '202510', extra: 5);
    committeeRecord($committee, '202603', extra: 3, meetingId: 7);

    $row = CommitteeHoursStatistics::for($root, 2026)->committees[0];

    expect($row['ytd'])->toMatchArray([
        'shifts' => 2,
        'extra' => 5,
        'meetings' => 3,
        'total' => 10,
    ]);
});

it('breaks out visitor interactions as a fourth grain, rolled up over the whole subtree', function () {
    // The fourth grain (ADR-0023 §6) reads the summary's composition rule from its one home —
    // a Sign-up's two counts plus the Group's extra interactions — and rolls it up the subtree
    // exactly as shifts, meetings and extra roll up, so a grandchild folds into its committee.
    $root = Group::factory()->create();
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    $grandchild = Group::factory()->create(['parent_id' => $committee->id]);

    committeeSignUp($committee, '202504', visitors: 20, extra: 5); // 25 from a signed-out shift
    committeeInteractions($committee, '202504', count: 3);         // 3 from extra interactions
    committeeSignUp($grandchild, '202504', visitors: 4);          // folds into the committee row

    $stats = CommitteeHoursStatistics::for($root, 2026);

    // The committee row reaches its whole subtree; the interactions grain sits beside the hours.
    expect($stats->committees[0]['months'][0]['interactions'])->toBe(32) // 25 + 3 + 4
        ->and($stats->committees[0]['ytd']['interactions'])->toBe(32)
        // The interactions grain never bleeds into the hours total.
        ->and($stats->committees[0]['months'][0]['total'])->toBe(0)
        // The org row is the complete department total, the whole subtree of the root.
        ->and($stats->org['months'][0]['interactions'])->toBe(32);
});

it('does not re-apply the Group hours multiplier — it is already baked into the stored hours', function () {
    // ROMWalks stores its walks already doubled (ADR-0022 §7): the multiplier is applied once
    // at recalculation, so the matrix reflects the stored numbers as-is and never doubles again.
    $root = Group::factory()->create();
    $walker = Group::factory()->create(['parent_id' => $root->id, 'hours_multiplier' => 2]);
    committeeRecord($walker, '202504', scheduled: 10); // 5 raw hours, already ×2 on store

    expect(CommitteeHoursStatistics::for($root, 2026)->committees[0]['months'][0]['shifts'])->toBe(10);
});

it('lists every scheduling Group for the summary scheduled section, at any depth', function () {
    // The scheduled section reads this list, not the committee rows. The DMV's top level is
    // page-less Container sections that run no scheduling (PRD #289), so a capability filter
    // over the root's children finds nothing while the programs beneath hold every shift hour.
    $root = Group::factory()->create();
    $section = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Programs', 'has_scheduling' => false]);
    Group::factory()->create(['parent_id' => $section->id, 'name' => 'Docents', 'has_scheduling' => true]);
    Group::factory()->create(['parent_id' => $section->id, 'name' => 'Office', 'has_scheduling' => false]);

    $names = collect(CommitteeHoursStatistics::for($root, 2026)->scheduling)->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['Docents']);
});

it('gives each scheduling Group its own hours, never a nested one twice', function () {
    $root = Group::factory()->create();
    $guides = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Guides', 'has_scheduling' => true]);
    $evening = Group::factory()->create(['parent_id' => $guides->id, 'name' => 'Guides Evening', 'has_scheduling' => true]);
    committeeRecord($guides, '202504', scheduled: 5);
    committeeRecord($evening, '202504', scheduled: 3);

    $byName = collect(CommitteeHoursStatistics::for($root, 2026)->scheduling)->keyBy('name');

    // The parent carries 5, not 8 — otherwise the section stops summing to the org total.
    expect($byName['Guides']['ytd']['shifts'])->toBe(5)
        ->and($byName['Guides Evening']['ytd']['shifts'])->toBe(3);
});
