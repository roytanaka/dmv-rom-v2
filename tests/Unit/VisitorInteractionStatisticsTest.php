<?php

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use App\Support\VisitorInteractionStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the one place the department's
 * headline visitor number is composed. A Group's figure for a month is the sum of that Group's
 * Sign-ups (visitor_count + extra_interaction_count) plus its extra interactions, rolled up
 * through the whole sub-Group subtree. Two sources, no per-Group special cases.
 *
 * This is the "service class with non-trivial logic" the conventions reserve a unit test for, so
 * it is exercised directly here — rows built by factory, no HTTP. Bucketing runs in PHP against
 * the Shift's `ends_at` and the record's `year_month`, so the result does not depend on the
 * engine's date handling.
 */
uses(TestCase::class, RefreshDatabase::class);

/** A signed-out Sign-up on a Shift owned by the Group, ending mid-month in the org zone. */
function visitorSignUp(Group $group, string $yearMonth, ?int $visitors = 0, ?int $extra = null): SignUp
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
function extraInteractions(Group $group, string $yearMonth, int $count): HoursRecord
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

/** The single listed row for the given Group name, or null. */
function visitorRow(VisitorInteractionStatistics $stats, string $name): ?array
{
    return collect($stats->groups)->firstWhere('name', $name);
}

it('labels twelve buckets in April-to-March order for the fiscal year', function () {
    $stats = VisitorInteractionStatistics::for(Group::factory()->create(), 2026);

    expect($stats->months)->toHaveCount(12)
        ->and($stats->months[0])->toBe('202504')
        ->and($stats->months[11])->toBe('202603');
});

it("sums a Group's Sign-up visitor and extra-interaction counts into its figure", function () {
    $root = Group::factory()->create();
    $docents = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Docents']);
    visitorSignUp($docents, '202504', visitors: 30, extra: 4);
    visitorSignUp($docents, '202504', visitors: 6, extra: 0);

    $row = visitorRow(VisitorInteractionStatistics::for($root, 2026), 'Docents');

    expect($row['months'][0])->toMatchArray(['year_month' => '202504', 'interactions' => 40]);
});

it("adds a Group's extra interactions from the Hours records", function () {
    $root = Group::factory()->create();
    $travel = Group::factory()->create(['parent_id' => $root->id, 'name' => 'ROM Travel']);
    extraInteractions($travel, '202504', 12);
    extraInteractions($travel, '202504', 3);

    $row = visitorRow(VisitorInteractionStatistics::for($root, 2026), 'ROM Travel');

    expect($row['months'][0]['interactions'])->toBe(15);
});

it('lists a Group with extra interactions and no scheduling at all', function () {
    // ROM Travel does not schedule; extra interactions are its whole presence in the number.
    $root = Group::factory()->create();
    $travel = Group::factory()->create(['parent_id' => $root->id, 'name' => 'ROM Travel', 'has_scheduling' => false]);
    extraInteractions($travel, '202504', 9);

    $names = collect(VisitorInteractionStatistics::for($root, 2026)->groups)->pluck('name');

    expect($names)->toContain('ROM Travel');
});

it('lists a Group with Sign-ups and no extra interactions', function () {
    $root = Group::factory()->create();
    $guides = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Visitor Guides']);
    visitorSignUp($guides, '202504', visitors: 22);

    $names = collect(VisitorInteractionStatistics::for($root, 2026)->groups)->pluck('name');

    expect($names)->toContain('Visitor Guides');
});

it('leaves a page-less Container section between the root and the programs off the report', function () {
    // The real org shape (PRD #289): the DMV's top level is page-less Container sections that
    // carry no visitor data of their own. The report lists the programs beneath them, never the
    // sections themselves, and folds the programs' subtree into their figure.
    $root = Group::factory()->create();
    $section = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Programs']);
    $docents = Group::factory()->create(['parent_id' => $section->id, 'name' => 'Docents']);
    visitorSignUp($docents, '202504', visitors: 5);

    $names = collect(VisitorInteractionStatistics::for($root, 2026)->groups)->pluck('name');

    expect($names)->toContain('Docents')
        ->and($names)->not->toContain('Programs');
});

it("rolls a Group's whole sub-Group subtree into its figure, to any depth", function () {
    $root = Group::factory()->create();
    $docents = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Docents']);
    $cohort = Group::factory()->create(['parent_id' => $docents->id, 'name' => 'Cohort']);
    $deep = Group::factory()->create(['parent_id' => $cohort->id, 'name' => 'Deep']);
    visitorSignUp($docents, '202504', visitors: 10);
    visitorSignUp($cohort, '202504', visitors: 3, extra: 1);
    extraInteractions($deep, '202504', 6);

    $row = visitorRow(VisitorInteractionStatistics::for($root, 2026), 'Docents');

    // 10 (own) + 4 (cohort sign-ups) + 6 (deep extra interactions), three levels down.
    expect($row['months'][0]['interactions'])->toBe(20)
        ->and($row['ytd'])->toBe(20);
});

it('reads a month with no data as zero, never a short list or a blank', function () {
    $root = Group::factory()->create();
    $docents = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Docents']);
    visitorSignUp($docents, '202510', visitors: 4); // October only

    $row = visitorRow(VisitorInteractionStatistics::for($root, 2026), 'Docents');

    expect($row['months'])->toHaveCount(12)
        ->and($row['months'][0]['interactions'])->toBe(0)  // April
        ->and($row['months'][6]['interactions'])->toBe(4)  // October
        ->and($row['ytd'])->toBe(4);
});

it("buckets a Sign-up by its Shift's ends_at, on the fiscal-year boundary", function () {
    $root = Group::factory()->create();
    $docents = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Docents']);
    visitorSignUp($docents, '202603', visitors: 3); // last month of Fiscal 2026
    visitorSignUp($docents, '202604', visitors: 4); // first month of Fiscal 2027

    expect(visitorRow(VisitorInteractionStatistics::for($root, 2026), 'Docents')['ytd'])->toBe(3)
        ->and(visitorRow(VisitorInteractionStatistics::for($root, 2027), 'Docents')['ytd'])->toBe(4);
});

it('marks a Group whose figures await a booking audience, and leaves the rest unmarked', function () {
    $root = Group::factory()->create();
    $romForYou = Group::factory()->awaitingBookingAudiences()->create(['parent_id' => $root->id, 'name' => 'ROMForYou']);
    $guides = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Visitor Guides']);
    visitorSignUp($romForYou, '202504', visitors: 293);
    visitorSignUp($guides, '202504', visitors: 100);

    $stats = VisitorInteractionStatistics::for($root, 2026);

    expect(visitorRow($stats, 'ROMForYou')['incomplete'])->toBeTrue()
        ->and(visitorRow($stats, 'Visitor Guides')['incomplete'])->toBeFalse();
});
