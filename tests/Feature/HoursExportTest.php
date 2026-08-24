<?php

use App\Enums\Category;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursRecord;
use App\Models\Member;

/*
 * CSV export across every Hours report (#414, PRD #406, ADR-0022 §8). The export is the same
 * numbers as the screen, behind the same gate as the screen — a Member without report access is
 * refused at the CSV route exactly as at the HTML one, so the export is never a way around the
 * gate that keeps per-Member hours from ordinary Members.
 *
 * The reports reuse the same payload builders as their HTML twins, so these tests pin the
 * export mechanism, the twelve-month + year-to-date column order, and the gate — the numbers
 * themselves are pinned by each report's own feature test.
 *
 * Frozen inside Fiscal 2026 (2025-04 through 2026-03) so the default window is fixed.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
});

/** A member of the given Group holding the given role. */
function exportOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function exportMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

/** A record at the no-meeting grain for one Member on one Group in one month. */
function exportRecord(Member $member, Group $group, string $yearMonth, int $scheduled, int $extra, int $meetingId = HoursRecord::NO_MEETING): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => $meetingId,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

/** The DMV root Group the six org-wide reports are always rooted at. */
function exportRoot(): Group
{
    return Group::factory()->create(['slug' => Group::ROOT_SLUG]);
}

/**
 * Parse a streamed CSV body into rows of string cells, stripping the UTF-8 BOM that
 * CsvExport::download writes and dropping the trailing blank line.
 *
 * @return list<list<string>>
 */
function csvRows(string $csv): array
{
    $csv = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;

    return collect(explode("\n", trim($csv)))
        ->map(fn (string $line) => str_getcsv($line, ',', '"', ''))
        ->all();
}

// --- The Group fiscal-year report ------------------------------------------------

it('exports the Group fiscal-year report as a CSV download', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);
    $alice = exportMemberOf($group);
    exportRecord($alice, $group, '202504', 2, 1); // April → bucket 0, total 3
    exportRecord($alice, $group, '202603', 0, 4); // March → bucket 11, total 4

    $response = $this->actingAs($chair)->get(route('groups.hours.report.csv', $group));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment');

    $csv = $response->streamedContent();
    // The Member row carries the twelve monthly totals in April-to-March order and its YTD.
    expect($csv)->toContain($alice->first_name);
    $line = collect(explode("\n", $csv))->first(fn (string $l) => str_contains($l, $alice->last_name));
    expect(str_getcsv($line, ',', '"', ''))->toBe([
        trim("{$alice->first_name} {$alice->last_name}"),
        '3', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '4', // Apr..Mar
        '7', // YTD
    ]);
});

it('carries the own and subtree rollups in the report CSV', function () {
    $group = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $group->id]);
    $chair = exportOfficerOf($group, Role::Chair);
    exportRecord(exportMemberOf($group), $group, '202504', 0, 3);
    exportRecord(Member::factory()->create(), $child, '202504', 0, 10);

    $rows = csvRows($this->actingAs($chair)->get(route('groups.hours.report.csv', $group))->streamedContent());

    $own = collect($rows)->first(fn (array $r) => $r[0] === __('hours.report.own'));
    $subtree = collect($rows)->first(fn (array $r) => $r[0] === __('hours.report.subtree'));
    expect($own[1])->toBe('3');       // own April
    expect($own[13])->toBe('3');      // own YTD
    expect($subtree[1])->toBe('13');  // subtree April
    expect($subtree[13])->toBe('13'); // subtree YTD
});

it('refuses an ordinary Member at the report CSV route, as at the HTML route', function () {
    $group = Group::factory()->create();

    $this->actingAs(exportMemberOf($group))
        ->get(route('groups.hours.report.csv', $group))
        ->assertForbidden();
});

it('lets a Chair of an ancestor Group export a descendant report', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(exportOfficerOf($parent, Role::Chair))
        ->get(route('groups.hours.report.csv', $child))
        ->assertOk();
});

it('redirects an unauthenticated visitor from the report CSV route to login', function () {
    $this->get(route('groups.hours.report.csv', Group::factory()->create()))
        ->assertRedirect(route('login'));
});

// --- The two Group summaries -----------------------------------------------------

it('exports the Member Extra Hours summary, counting only no-meeting rows', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);
    $alice = exportMemberOf($group);
    exportRecord($alice, $group, '202504', 0, 3);            // extra, no meeting → counts
    exportRecord($alice, $group, '202504', 0, 9, meetingId: 7); // meeting hours → excluded

    $rows = csvRows($this->actingAs($chair)->get(route('groups.hours.extra.csv', $group))->streamedContent());
    $row = collect($rows)->first(fn (array $r) => $r[0] === trim("{$alice->first_name} {$alice->last_name}"));
    expect($row[1])->toBe('3');  // April extra
    expect($row[13])->toBe('3'); // YTD
});

it('exports the Member Meeting Hours summary, counting only meeting rows', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);
    $alice = exportMemberOf($group);
    exportRecord($alice, $group, '202504', 0, 3);            // extra, no meeting → excluded
    exportRecord($alice, $group, '202504', 0, 9, meetingId: 7); // meeting hours → counts

    $rows = csvRows($this->actingAs($chair)->get(route('groups.hours.meetings.csv', $group))->streamedContent());
    $row = collect($rows)->first(fn (array $r) => $r[0] === trim("{$alice->first_name} {$alice->last_name}"));
    expect($row[1])->toBe('9');  // April meeting hours
    expect($row[13])->toBe('9'); // YTD
});

it('refuses an ordinary Member at the summary CSV routes', function () {
    $group = Group::factory()->create();
    $member = exportMemberOf($group);

    $this->actingAs($member)->get(route('groups.hours.extra.csv', $group))->assertForbidden();
    $this->actingAs($member)->get(route('groups.hours.meetings.csv', $group))->assertForbidden();
});

// --- The month picker ------------------------------------------------------------

it('exports one month\'s entries across the Group, a row per Member', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);
    $alice = exportMemberOf($group);
    exportRecord($alice, $group, '202504', 2, 3);

    $rows = csvRows($this->actingAs($chair)->get(route('groups.hours.month.csv', ['group' => $group, 'month' => '202504']))->streamedContent());
    $row = collect($rows)->first(fn (array $r) => $r[0] === trim("{$alice->first_name} {$alice->last_name}"));
    expect($row)->toBe([trim("{$alice->first_name} {$alice->last_name}"), '2', '3', '5']); // scheduled, extra, total
});

it('refuses an ordinary Member at the month CSV route', function () {
    $group = Group::factory()->create();

    $this->actingAs(exportMemberOf($group))
        ->get(route('groups.hours.month.csv', $group))
        ->assertForbidden();
});

// --- Member History --------------------------------------------------------------

it('exports one Member\'s history in the Group, newest first', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);
    $alice = exportMemberOf($group);
    exportRecord($alice, $group, '202504', 1, 0);
    exportRecord($alice, $group, '202505', 0, 4);

    $rows = csvRows($this->actingAs($chair)->get(route('groups.hours.member.csv', ['group' => $group, 'member' => $alice->id]))->streamedContent());
    // Header, then May (newest) then April.
    expect($rows[1][1])->toBe('0'); // May scheduled
    expect($rows[1][2])->toBe('4'); // May extra
    expect($rows[2][1])->toBe('1'); // April scheduled
});

it('refuses an ordinary Member at the member-history CSV route', function () {
    $group = Group::factory()->create();

    $this->actingAs(exportMemberOf($group))
        ->get(route('groups.hours.member.csv', $group))
        ->assertForbidden();
});

// --- The six DMV-wide reports ----------------------------------------------------

it('exports Summary Committee Statistics with the scheduled section and org-wide rows', function () {
    $root = exportRoot();
    $walker = Group::factory()->create(['parent_id' => $root->id, 'name' => 'ROMWalks', 'has_scheduling' => true]);
    $office = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Office', 'has_scheduling' => false]);
    exportRecord(Member::factory()->create(), $walker, '202504', 8, 0);
    exportRecord(Member::factory()->create(), $office, '202504', 0, 3);
    exportRecord(Member::factory()->create(), $office, '202504', 0, 5, meetingId: 12);

    $rows = csvRows($this->actingAs(exportOfficerOf($root, Role::Chair))->get(route('hours.committee-summary.csv'))->streamedContent());

    $walkerRow = collect($rows)->first(fn (array $r) => $r[0] === 'ROMWalks');
    expect($walkerRow[1])->toBe('8');  // April scheduled
    expect(collect($rows)->contains(fn (array $r) => $r[0] === 'Office'))->toBeFalse(); // non-scheduling omitted
    $total = collect($rows)->first(fn (array $r) => $r[0] === __('hours.dmv.summary.total'));
    expect($total[1])->toBe('16'); // 8 scheduled + 3 extra + 5 meeting
});

it('exports Detailed Committee Statistics broken into shifts, meetings and extra', function () {
    $root = exportRoot();
    $committee = Group::factory()->create(['parent_id' => $root->id, 'name' => 'Docents']);
    exportRecord(Member::factory()->create(), $committee, '202504', 4, 0);
    exportRecord(Member::factory()->create(), $committee, '202504', 0, 2, meetingId: 9);
    exportRecord(Member::factory()->create(), $committee, '202504', 0, 6);

    $rows = csvRows($this->actingAs(exportOfficerOf($root, Role::Statistician))->get(route('hours.committee-detailed.csv'))->streamedContent());
    // The three Docents rows carry the name in column 0 and the kind in column 1.
    $docents = collect($rows)->filter(fn (array $r) => $r[0] === 'Docents')->values();
    expect($docents)->toHaveCount(3);
    expect($docents[0][1])->toBe(__('hours.dmv.detailed.kind.shifts'));
    expect($docents[0][2])->toBe('4');   // shifts April
    expect($docents[1][2])->toBe('2');   // meetings April
    expect($docents[2][2])->toBe('6');   // extra April
});

it('exports Active Members Ranked Hours, then the members with no hours', function () {
    $root = exportRoot();
    $group = Group::factory()->create(['parent_id' => $root->id]);
    $bob = Member::factory()->create(['first_name' => 'Bob', 'last_name' => 'Ranked', 'category' => Category::Active]);
    Member::factory()->create(['first_name' => 'Carol', 'last_name' => 'Absent', 'category' => Category::Active]);
    exportRecord($bob, $group, '202504', 0, 10);

    $rows = csvRows($this->actingAs(exportOfficerOf($root, Role::Chair))->get(route('hours.ranked.csv'))->streamedContent());
    expect($rows[1])->toBe(['Bob Ranked', '0', '10', '10']);
    // Carol appears under the no-hours section label, as a bare name.
    $labelIndex = collect($rows)->search(fn (array $r) => $r[0] === __('hours.dmv.ranked.no_hours'));
    expect($labelIndex)->not->toBeFalse();
    expect(collect($rows)->slice($labelIndex + 1)->contains(fn (array $r) => $r[0] === 'Carol Absent'))->toBeTrue();
});

it('exports a zero-hours list as one name per row', function () {
    $root = exportRoot();
    $group = Group::factory()->create(['parent_id' => $root->id]);
    Member::factory()->create(['first_name' => 'Idle', 'last_name' => 'Member', 'category' => Category::Active]);
    $worker = Member::factory()->create(['first_name' => 'Busy', 'last_name' => 'Member', 'category' => Category::Active]);
    exportRecord($worker, $group, '202504', 0, 5);

    $rows = csvRows($this->actingAs(exportOfficerOf($root, Role::Chair))->get(route('hours.zero-hours.csv'))->streamedContent());
    $names = collect($rows)->map(fn (array $r) => $r[0]);
    expect($names->contains('Idle Member'))->toBeTrue();   // zero total → listed
    expect($names->contains('Busy Member'))->toBeFalse();  // worked hours → not listed
});

it('refuses an ordinary Member at every DMV CSV route', function (string $routeName) {
    exportRoot();

    $this->actingAs(Member::factory()->create())->get(route($routeName))->assertForbidden();
})->with([
    'hours.committee-summary.csv',
    'hours.committee-detailed.csv',
    'hours.ranked.csv',
    'hours.zero-hours.csv',
    'hours.zero-shift-hours.csv',
    'hours.zero-extra-hours.csv',
]);

// --- Bilingual --------------------------------------------------------------------

it('resolves a CSV export under the French /fr/ path', function () {
    $group = Group::factory()->create();
    $chair = exportOfficerOf($group, Role::Chair);

    $this->withLocaleRoutes('fr', function () use ($chair, $group) {
        $response = $this->actingAs($chair)->get("/fr/groupes/{$group->slug}/heures/rapport.csv");
        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('text/csv');
    });
});
