<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the department's headline visitor
 * number, returned as Groups × twelve months over a fiscal year. Exercised through the route →
 * auth middleware → viewVisitorSummary policy → Gate::before, and at the Inertia prop seam.
 *
 * Unlike the other DMV-wide reports this one is the named exception (ADR-0023 §6): open to any
 * signed-in Member. It is an aggregate with no personal data, and it is legacy's own choice — the
 * sole item members who fail the officer test still reach. Officers additionally see it in the org
 * report nav where the rest of the family lives.
 *
 * Frozen inside Fiscal 2026 (2025-04 through 2026-03) so the default window is fixed.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
    $this->root = Group::factory()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV']);
});

/** A member of the given Group holding the given role. */
function summaryOfficer(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** A signed-out Sign-up on a Shift owned by the Group, ending mid-month in the org zone. */
function summarySignUp(Group $group, string $yearMonth, int $visitors = 0, ?int $extra = null): SignUp
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

// --- Who may read the report ------------------------------------------------

it('opens the report to any signed-in Member', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('hours.visitor-summary'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('hours/VisitorSummary'));
});

it('redirects an unauthenticated visitor to login', function () {
    $this->get(route('hours.visitor-summary'))->assertRedirect(route('login'));
});

it('tells the page an ordinary Member may not reach the officer report nav', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('hours.visitor-summary'))
        ->assertInertia(fn (Assert $page) => $page->where('canViewOrgReports', false));
});

it('tells the page a DMV officer may reach the officer report nav', function () {
    $this->actingAs(summaryOfficer($this->root, Role::Chair))
        ->get(route('hours.visitor-summary'))
        ->assertInertia(fn (Assert $page) => $page->where('canViewOrgReports', true));
});

// --- The numbers ------------------------------------------------------------

it('renders Groups × twelve months with a year-to-date, composed from both sources', function () {
    $section = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Programs']);
    $docents = Group::factory()->create(['parent_id' => $section->id, 'name' => 'Docents']);
    summarySignUp($docents, '202504', visitors: 30, extra: 5);
    HoursRecord::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'group_id' => $docents->id,
        'year_month' => '202504',
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => 0, 'extra_hours' => 0, 'total_hours' => 0,
        'extra_interactions' => 7,
    ]);

    $this->actingAs(Member::factory()->create())
        ->get(route('hours.visitor-summary'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.name', 'Docents')
            ->where('groups.0.months.0.interactions', 42) // 30 + 5 + 7
            ->where('groups.0.ytd', 42)
            ->has('months', 12)
            ->where('months.0.year_month', '202504'));
});

it('marks a Group whose figures await a booking audience', function () {
    $romForYou = Group::factory()->awaitingBookingAudiences()->create(['parent_id' => $this->root->id, 'name' => 'ROMForYou']);
    summarySignUp($romForYou, '202504', visitors: 293);

    $this->actingAs(Member::factory()->create())
        ->get(route('hours.visitor-summary'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.name', 'ROMForYou')
            ->where('groups.0.incomplete', true));
});

// --- CSV export -------------------------------------------------------------

/**
 * Parse a streamed CSV body into rows of string cells, stripping the UTF-8 BOM and the
 * trailing blank line.
 *
 * @return list<list<string>>
 */
function summaryCsvRows(string $csv): array
{
    $csv = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;

    return collect(explode("\n", trim($csv)))
        ->map(fn (string $line) => str_getcsv($line, ',', '"', ''))
        ->all();
}

it('exports the report as a CSV whose numbers match the screen, cell for cell', function () {
    $docents = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Docents']);
    summarySignUp($docents, '202504', visitors: 30, extra: 5);
    HoursRecord::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'group_id' => $docents->id,
        'year_month' => '202504',
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => 0, 'extra_hours' => 0, 'total_hours' => 0,
        'extra_interactions' => 7,
    ]);

    $response = $this->actingAs(Member::factory()->create())->get(route('hours.visitor-summary.csv'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment');

    $rows = summaryCsvRows($response->streamedContent());
    $docentsRow = collect($rows)->first(fn (array $r) => $r[0] === 'Docents');
    // Group name, then April (42 = 30 + 5 + 7), eleven zero months, then the year-to-date.
    expect($docentsRow)->toBe(['Docents', '42', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '42']);
});

it('opens the CSV export to any signed-in Member, exactly as the screen is', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('hours.visitor-summary.csv'))
        ->assertOk();
});

it('redirects an unauthenticated visitor from the CSV route to login', function () {
    $this->get(route('hours.visitor-summary.csv'))->assertRedirect(route('login'));
});

// --- Fiscal-year window and locale ------------------------------------------

it('defaults to the current fiscal year and moves to a picked one', function () {
    $docents = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Docents']);
    summarySignUp($docents, '202309', visitors: 7); // Fiscal 2024 runs 2023-04 through 2024-03

    $member = Member::factory()->create();

    $this->actingAs($member)->get(route('hours.visitor-summary'))
        ->assertInertia(fn (Assert $page) => $page->where('fiscalYear', OrgTime::currentFiscalYear()));

    $this->actingAs($member)->get(route('hours.visitor-summary', ['fy' => 2024]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYear', 2024)
            ->where('months.0.year_month', '202304')
            ->where('groups.0.ytd', 7));
});

it('resolves the report under the French /fr/ path', function () {
    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(Member::factory()->create())
            ->get('/fr/heures/interactions-visiteurs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('hours/VisitorSummary')->where('locale', 'fr'));
    });
});
