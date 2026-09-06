<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Http\Requests\RecalculateHoursRequest;
use App\Http\Requests\StoreHoursRecordRequest;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\CommitteeHoursStatistics;
use App\Support\CsvExport;
use App\Support\GroupHoursMatrix;
use App\Support\OrgTime;
use App\Support\VisitorInteractionStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Extra-hours entry (#408, PRD #406, ADR-0022 §2) — the write seam parallel to the Hours
 * tab's read surface on {@see GroupController}. The mutation is structurally authorized in
 * {@see StoreHoursRecordRequest}, which delegates to the HoursRecordPolicy.
 *
 * The one non-negotiable deviation from legacy (ADR-0022 §4): the hours are always written
 * for the authenticated Member. `$request->user()` is both the whose-hours and the author;
 * a `member_id` in the request body never reaches the write, because the model method takes
 * the Member object, not an id from input.
 */
class HoursController extends Controller
{
    /**
     * Add extra hours and/or extra interactions for the authenticated Member on a Group, in
     * one of the two open months. Both parts are additive and floor at zero; a blank or zero
     * entry (or a correction the floor swallows) writes nothing at all, and a submit that is a
     * no-op on both leaves nothing behind. Extra interactions (ADR-0023 §6) — visitors served
     * outside a Shift — ride the same form and land in a column of their own, outside
     * `total_hours`. Every real write appends its field-tagged Hours adjustment in the same
     * transaction ({@see HoursRecord::enterExtras()}).
     */
    public function store(StoreHoursRecordRequest $request, Group $group): RedirectResponse
    {
        $actor = $request->user();

        HoursRecord::enterExtras(
            $actor,
            $group,
            $request->validated('year_month'),
            (int) $request->validated('hours'),
            (int) $request->validated('interactions'),
            $actor,
        );

        return back();
    }

    /**
     * Recalculate a Group's scheduled hours for a month from its Sign-ups (#410, PRD #406,
     * ADR-0022 §2) — the derived-half write, parallel to the extra-hours entry above. A Chair
     * or Scheduler of a scheduling Group runs it; {@see RecalculateHoursRequest} authorizes
     * against the scheduling gate and bounds the month to the current fiscal year, and the
     * model method replaces (never adds) the scheduled hours, leaving extra hours and the
     * adjustment log untouched.
     */
    public function recalculate(RecalculateHoursRequest $request, Group $group): RedirectResponse
    {
        HoursRecord::recalculateScheduled($group, $request->validated('year_month'));

        return back();
    }

    /**
     * The My Hours destination (#409, PRD #406, ADR-0022 §8) — the authenticated Member's own
     * hours, gathered from every Group they have hours in, broken out by month across one
     * fiscal year with a year-to-date total. This is the surface that answers the renewal
     * question ("how many hours have I put in this year?") and it lives outside any Group.
     *
     * It reads the authenticated Member alone — never another Member's hours, here or
     * anywhere (§4). The fiscal-year window is the `?fy=` query param, defaulting to the
     * current fiscal year on the org wall clock. The fiscal-year definition and the twelve
     * ordered month buckets come from {@see OrgTime}, the one shared home for both (§8), so
     * the report tickets that follow reuse the same boundaries.
     *
     * Scheduled hours read zero until recalculation ships — correct and honest, not broken.
     */
    public function mine(Request $request): Response
    {
        $member = $request->user();

        $fiscalYear = $request->integer('fy') ?: OrgTime::currentFiscalYear();
        $months = OrgTime::fiscalYearMonths($fiscalYear);

        $records = HoursRecord::query()
            ->where('member_id', $member->getKey())
            ->whereIn('year_month', $months)
            ->with('group')
            ->get();

        return Inertia::render('MyHours', [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->pickableFiscalYears($member->getKey()),
            'months' => array_map(fn (string $yearMonth): array => [
                'year_month' => $yearMonth,
                'month' => $this->monthStart($yearMonth),
            ], $months),
            'groups' => $this->groupsMatrix($records, $months),
        ]);
    }

    /**
     * A Group's fiscal-year hours report (#411, PRD #406, ADR-0022 §5) — a Member × twelve-month
     * matrix with a year-to-date column, and the Group's own hours next to its hours including
     * every descendant. This is the surface a Chair or Statistician uses to see who on their
     * roster has done what.
     *
     * Reports are not open reading (§4): the read is gated to a Chair or Statistician of the
     * Group or any ancestor, or the super-tier, via the HoursRecordPolicy's `viewReports` — an
     * ordinary Member and a sibling-Group Member are both refused with a 403, so no per-Member
     * hours and not even a Group total leak to a peer.
     *
     * The own-versus-subtree rollup and its year-to-date totals come from the {@see
     * GroupHoursMatrix} query object, the feature's one seam (§5); the per-Member rows are the
     * Group's own records folded by Member. The fiscal-year window is the `?fy=` query param,
     * defaulting to the current fiscal year on the org wall clock, and its boundaries come from
     * {@see OrgTime} — shared with My Hours so the columns line up everywhere.
     */
    public function report(Request $request, Group $group): Response
    {
        return Inertia::render('groups/HoursReport', $this->reportPayload($request, $group));
    }

    /**
     * The Group fiscal-year report as a CSV download (#414, ADR-0022 §8) — the same numbers the
     * screen shows, from the same payload, behind the same `viewReports` gate: an ordinary Member
     * is refused here exactly as at the HTML route. The file carries a Member × twelve-month matrix
     * in April-to-March order with a year-to-date column, then the own and subtree rollup rows.
     */
    public function reportCsv(Request $request, Group $group): StreamedResponse
    {
        $payload = $this->reportPayload($request, $group);

        $rows = [
            [__('hours.report.column.member'), ...$this->monthHeadings($payload['months']), __('hours.report.column.ytd')],
        ];
        foreach ($payload['members'] as $member) {
            $rows[] = [$member['name'], ...array_column($member['months'], 'total_hours'), $member['ytd']['total_hours']];
        }
        $rows[] = [__('hours.report.own'), ...$payload['totals']['own']['months'], $payload['totals']['own']['ytd']];
        $rows[] = [__('hours.report.subtree'), ...$payload['totals']['subtree']['months'], $payload['totals']['subtree']['ytd']];

        return CsvExport::download($this->csvName($group->slug, 'hours-report', "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * The Group fiscal-year report's payload — the `viewReports` gate and the numbers, shared by
     * the HTML page and its CSV twin so the two can never diverge (ADR-0022 §8).
     *
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request, Group $group): array
    {
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $fiscalYear = $request->integer('fy') ?: OrgTime::currentFiscalYear();
        $months = OrgTime::fiscalYearMonths($fiscalYear);
        $matrix = GroupHoursMatrix::for($group, $fiscalYear);

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->whereIn('year_month', $months)
            ->with('member')
            ->get();

        return [
            'group' => $this->groupPayload($group),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->reportFiscalYears($group),
            'months' => $this->monthColumns($months),
            'members' => $this->membersMatrix($records, $months),
            'totals' => [
                'own' => ['months' => $matrix->own, 'ytd' => $matrix->ownYtd],
                'subtree' => ['months' => $matrix->subtree, 'ytd' => $matrix->subtreeYtd],
            ],
        ];
    }

    /**
     * The month picker (#412, PRD #406, ADR-0022 §8) — one month's entries across the whole
     * Group, a row per Member. This is what a Statistician opens when the fiscal-year numbers
     * look wrong and they want to know which month moved: a single month, every Member's
     * scheduled/extra/total side by side.
     *
     * Gated identically to the fiscal-year report (§4) via `viewReports`. The month in view is
     * the `?month=YYYYMM` query param, defaulting to the current month on the org wall clock;
     * the picker offers every month the Group has a record in plus the current one, newest
     * first, so an out-of-band value simply lands on a month with no rows rather than erroring.
     * The rows are the Group's own records for that month folded by Member (all grains summed),
     * name-ordered for a stable display.
     */
    public function month(Request $request, Group $group): Response
    {
        return Inertia::render('groups/HoursMonth', $this->monthPayload($request, $group));
    }

    /**
     * The month picker as a CSV download (#414, ADR-0022 §8) — one month's entries across the
     * Group, a row per Member with scheduled, extra, and total, from the same payload and behind
     * the same `viewReports` gate as the screen.
     */
    public function monthCsv(Request $request, Group $group): StreamedResponse
    {
        $payload = $this->monthPayload($request, $group);

        $rows = [[
            __('hours.detail.month.column.member'),
            __('hours.detail.month.column.scheduled'),
            __('hours.detail.month.column.extra'),
            __('hours.detail.month.column.total'),
        ]];
        foreach ($payload['members'] as $member) {
            $rows[] = [$member['name'], $member['scheduled_hours'], $member['extra_hours'], $member['total_hours']];
        }

        return CsvExport::download($this->csvName($group->slug, 'hours-month', $payload['month']['year_month']), $rows);
    }

    /**
     * The month picker's payload — the `viewReports` gate and the numbers, shared by the HTML page
     * and its CSV twin.
     *
     * @return array<string, mixed>
     */
    private function monthPayload(Request $request, Group $group): array
    {
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $pickable = $this->pickableMonths($group);
        $yearMonth = $request->string('month')->toString() ?: OrgTime::now()->format('Ym');

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->where('year_month', $yearMonth)
            ->with('member')
            ->get();

        return [
            'group' => $this->groupPayload($group),
            'month' => ['year_month' => $yearMonth, 'month' => $this->monthStart($yearMonth)],
            'months' => array_map(fn (string $ym): array => [
                'year_month' => $ym,
                'month' => $this->monthStart($ym),
            ], $pickable),
            'members' => $records
                ->groupBy('member_id')
                ->map(function (Collection $memberRecords): array {
                    $member = $memberRecords->first()->member;
                    $scheduled = (int) $memberRecords->sum('scheduled_hours');
                    $extra = (int) $memberRecords->sum('extra_hours');

                    return [
                        'id' => $member->getKey(),
                        'name' => trim("{$member->first_name} {$member->last_name}"),
                        'scheduled_hours' => $scheduled,
                        'extra_hours' => $extra,
                        'total_hours' => $scheduled + $extra,
                    ];
                })
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }

    /**
     * Member History (#412, PRD #406, ADR-0022 §8) — one Member's hours in this Group over time.
     * This is what a Chair opens before a standing conversation, and it is the only place one
     * Member's record is legible to someone else — so it lives behind the same `viewReports`
     * gate as every other report (§4), never open reading.
     *
     * The Member in view is the `?member=<id>` query param, but only a Member who actually has
     * hours in this Group can be picked: an id outside that set resolves to no selection, so the
     * page never discloses whether an arbitrary Member exists. Their records are folded by month
     * (all grains summed) and returned newest first — legible history, not a raw row dump.
     */
    public function memberHistory(Request $request, Group $group): Response
    {
        return Inertia::render('groups/HoursMemberHistory', $this->memberHistoryPayload($request, $group));
    }

    /**
     * Member History as a CSV download (#414, ADR-0022 §8) — one Member's hours in this Group over
     * time, newest first, from the same payload and behind the same `viewReports` gate. With no
     * Member picked the file is the header alone, disclosing nothing, exactly as the screen shows
     * a prompt rather than a list.
     */
    public function memberHistoryCsv(Request $request, Group $group): StreamedResponse
    {
        $payload = $this->memberHistoryPayload($request, $group);

        $rows = [[
            __('hours.detail.member.column.month'),
            __('hours.detail.member.column.scheduled'),
            __('hours.detail.member.column.extra'),
            __('hours.detail.member.column.total'),
        ]];
        foreach ($payload['rows'] as $row) {
            $month = CarbonImmutable::parse($row['month'])->locale(app()->getLocale())->isoFormat('MMMM YYYY');
            $rows[] = [$month, $row['scheduled_hours'], $row['extra_hours'], $row['total_hours']];
        }

        $suffix = $payload['member'] !== null ? "member-{$payload['member']['id']}" : 'member';

        return CsvExport::download($this->csvName($group->slug, 'hours-history', $suffix), $rows);
    }

    /**
     * Member History's payload — the `viewReports` gate and the numbers, shared by the HTML page
     * and its CSV twin.
     *
     * @return array<string, mixed>
     */
    private function memberHistoryPayload(Request $request, Group $group): array
    {
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->with('member')
            ->get();

        // The Members with any hours in this Group, name-ordered, for the picker dropdown.
        $members = $records
            ->groupBy('member_id')
            ->map(function (Collection $memberRecords): array {
                $member = $memberRecords->first()->member;

                return [
                    'id' => $member->getKey(),
                    'name' => trim("{$member->first_name} {$member->last_name}"),
                ];
            })
            ->sortBy('name')
            ->values();

        // Only a Member with hours here may be picked — an id outside the set discloses nothing.
        $pickedId = $request->integer('member') ?: null;
        $picked = $pickedId !== null ? $records->firstWhere('member_id', $pickedId) : null;

        return [
            'group' => $this->groupPayload($group),
            'members' => $members->all(),
            'member' => $picked !== null
                ? ['id' => $picked->member->getKey(), 'name' => trim("{$picked->member->first_name} {$picked->member->last_name}")]
                : null,
            'rows' => $picked === null ? [] : $records
                ->where('member_id', $picked->member_id)
                ->groupBy('year_month')
                ->map(function (Collection $monthRecords, string $yearMonth): array {
                    $scheduled = (int) $monthRecords->sum('scheduled_hours');
                    $extra = (int) $monthRecords->sum('extra_hours');

                    return [
                        'year_month' => $yearMonth,
                        'month' => $this->monthStart($yearMonth),
                        'scheduled_hours' => $scheduled,
                        'extra_hours' => $extra,
                        'total_hours' => $scheduled + $extra,
                    ];
                })
                ->sortByDesc('year_month')
                ->values()
                ->all(),
        ];
    }

    /**
     * The Member Extra Hours summary (#412, PRD #406, ADR-0022 §8) — a Member × twelve-month
     * matrix of the extra hours each Member self-entered this fiscal year, read from the Group's
     * records carrying **no** Meeting ({@see HoursRecord::NO_MEETING}). This is "what people told
     * us they did", and legacy keeps it apart from meeting attendance deliberately.
     *
     * Gated identically to the fiscal-year report (§4). The window is the `?fy=` query param,
     * defaulting to the current fiscal year; its twelve April-to-March buckets come from
     * {@see OrgTime}, so the columns line up with every other report.
     */
    public function extraSummary(Request $request, Group $group): Response
    {
        return Inertia::render('groups/HoursExtraSummary', $this->summaryPayload($request, $group, withMeeting: false));
    }

    /**
     * The Member Extra Hours summary as a CSV download (#414, ADR-0022 §8) — the same Member ×
     * twelve-month matrix the screen shows, from the same payload, behind the same `viewReports`
     * gate.
     */
    public function extraSummaryCsv(Request $request, Group $group): StreamedResponse
    {
        return $this->summaryCsv($this->summaryPayload($request, $group, withMeeting: false), $group, 'extra-hours');
    }

    /**
     * The Member Meeting Hours summary (#412, PRD #406, ADR-0022 §8) — the twin of
     * {@see extraSummary()}, read instead from the Group's records **carrying** a Meeting
     * (`meeting_id` other than {@see HoursRecord::NO_MEETING}). This is "what people showed up
     * to", stored in `extra_hours` on meeting rows exactly as legacy imports them.
     *
     * Meeting-hours entry is out of this pass (§2) — legacy rows import and this reads them, but
     * nothing records new ones after cutover, so a recent fiscal year renders empty rather than
     * erroring: the honest answer, not a bug.
     */
    public function meetingSummary(Request $request, Group $group): Response
    {
        return Inertia::render('groups/HoursMeetingSummary', $this->summaryPayload($request, $group, withMeeting: true));
    }

    /**
     * The Member Meeting Hours summary as a CSV download (#414, ADR-0022 §8) — the twin of
     * {@see extraSummaryCsv()}, read instead from the Group's records carrying a Meeting.
     */
    public function meetingSummaryCsv(Request $request, Group $group): StreamedResponse
    {
        return $this->summaryCsv($this->summaryPayload($request, $group, withMeeting: true), $group, 'meeting-hours');
    }

    /**
     * Summary Committee Statistics (#413, PRD #406, ADR-0022 §8) — the first of the six
     * DMV-wide reports, and the single output the whole Hours feature exists to produce.
     * Groups × twelve months of **scheduled hours**, then org-wide rows underneath for meeting
     * hours, extra hours, and the grand total.
     *
     * The scheduled section lists every Group that runs scheduling ({@see Group::$has_scheduling}),
     * wherever it sits in the tree — a Group with no schedule can never carry a number there, so
     * an empty row would be noise. It reads the flat scheduling list rather than the committee
     * rows: the DMV's top level is page-less Container sections (PRD #289) that run no scheduling,
     * so filtering the committees by the capability listed nothing at all while the programs below
     * them held every shift hour in the department. The three org-wide rows read the DMV root's
     * whole subtree (the complete org total) from {@see CommitteeHoursStatistics}.
     *
     * Gated to the DMV root Group's Chair, Secretary, or Statistician, the Records stewardship,
     * or the super-tier (§4) via `viewOrgReports` — an ordinary Member reaches none of the six.
     */
    public function committeeSummary(Request $request): Response
    {
        return Inertia::render('hours/CommitteeSummary', $this->committeeSummaryPayload($request));
    }

    /**
     * Summary Committee Statistics as a CSV download (#414, ADR-0022 §8) — the same scheduled-hours
     * matrix and the same three org-wide rows the screen shows, from the same payload and behind
     * the same `viewOrgReports` gate an ordinary Member never passes.
     */
    public function committeeSummaryCsv(Request $request): StreamedResponse
    {
        $payload = $this->committeeSummaryPayload($request);
        $headings = $this->monthHeadings($payload['months']);

        $rows = [[__('hours.dmv.summary.column.committee'), ...$headings, __('hours.dmv.summary.column.ytd')]];
        $rows[] = [__('hours.dmv.summary.scheduled')];
        foreach ($payload['scheduled'] as $committee) {
            $rows[] = [$committee['name'], ...$committee['months'], $committee['ytd']];
        }
        foreach (['meetings', 'extra', 'total'] as $part) {
            $rows[] = [__("hours.dmv.summary.{$part}"), ...$payload['orgRows'][$part]['months'], $payload['orgRows'][$part]['ytd']];
        }

        return CsvExport::download($this->csvName(null, 'committee-summary', "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * Summary Committee Statistics' payload — the `viewOrgReports` gate and the numbers, shared by
     * the HTML page and its CSV twin.
     *
     * @return array<string, mixed>
     */
    private function committeeSummaryPayload(Request $request): array
    {
        $this->authorizeOrgReports($request);

        [$fiscalYear, $stats] = $this->orgStatistics($request);

        return [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'months' => $this->monthColumns($stats->months),
            // Every Group that runs scheduling, at any depth, carrying its own scheduled hours
            // — not the root's direct children, which are page-less Container sections that
            // run no scheduling and so matched nothing.
            'scheduled' => collect($stats->scheduling)
                ->map(fn (array $group): array => [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'months' => array_column($group['months'], 'shifts'),
                    'ytd' => $group['ytd']['shifts'],
                ])
                ->values()
                ->all(),
            'orgRows' => [
                'meetings' => $this->orgRow($stats->org, 'meetings'),
                'extra' => $this->orgRow($stats->org, 'extra'),
                'total' => $this->orgRow($stats->org, 'total'),
            ],
        ];
    }

    /**
     * Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the department's headline
     * visitor number, returned as Groups × twelve months over a fiscal year with a year-to-date
     * column. Each Group's figure is its Sign-ups' two counts plus its extra interactions, rolled
     * up through the whole sub-Group subtree; the composition rule lives in exactly one place,
     * {@see VisitorInteractionStatistics}. The month window is {@see OrgTime}'s, shared with the
     * hours reports, so two numbers on one screen cover the same period.
     *
     * The one DMV-wide report that is **not** officer-gated (ADR-0023 §6): open to any signed-in
     * Member via the named `viewVisitorSummary` exception. Ordinary Members reach it from My Hours;
     * officers additionally see it in the org report nav, so the page is told whether the viewer
     * may reach that nav (`canViewOrgReports`) rather than linking a family of reports an ordinary
     * Member is forbidden. The root is resolved by its single reserved slug; a 404 if unseeded.
     */
    public function visitorSummary(Request $request): Response
    {
        abort_unless($request->user()->can('viewVisitorSummary', HoursRecord::class), 403);

        $fiscalYear = $this->fiscalYear($request);
        $root = Group::where('slug', Group::ROOT_SLUG)->firstOrFail();
        $stats = VisitorInteractionStatistics::for($root, $fiscalYear);

        return Inertia::render('hours/VisitorSummary', [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'months' => $this->monthColumns($stats->months),
            'groups' => $stats->groups,
            'canViewOrgReports' => $request->user()->can('viewOrgReports', HoursRecord::class),
        ]);
    }

    /**
     * Detailed Committee Statistics (#413, PRD #406, ADR-0022 §8) — the same twelve months, but
     * each committee broken into shifts, meetings, and extra hours, so a reader can see where a
     * committee's hours came from. Each committee row rolls up its whole subtree.
     *
     * The DMV's own row includes its sub-Groups — the root's subtree is the whole department —
     * so the org total is complete: nothing logged against the root or a Group outside a listed
     * committee is orphaned. Gated identically to the summary (§4) via `viewOrgReports`.
     */
    public function committeeDetailed(Request $request): Response
    {
        return Inertia::render('hours/CommitteeDetailed', $this->committeeDetailedPayload($request));
    }

    /**
     * Detailed Committee Statistics as a CSV download (#414, ADR-0022 §8) — each committee's
     * shifts, meetings, and extra broken out across the twelve months, then the DMV total's three
     * rows, from the same payload and behind the same `viewOrgReports` gate.
     */
    public function committeeDetailedCsv(Request $request): StreamedResponse
    {
        $payload = $this->committeeDetailedPayload($request);
        $headings = $this->monthHeadings($payload['months']);

        $rows = [[
            __('hours.dmv.detailed.column.committee'),
            __('hours.dmv.detailed.column.kind'),
            ...$headings,
            __('hours.dmv.detailed.column.ytd'),
        ]];
        foreach ($payload['committees'] as $committee) {
            array_push($rows, ...$this->detailedRows($committee['name'], $committee));
        }
        array_push($rows, ...$this->detailedRows(__('hours.dmv.detailed.total'), $payload['org']));

        return CsvExport::download($this->csvName(null, 'committee-detailed', "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * The three CSV rows for one committee (or the DMV total) in the Detailed report — one per kind
     * (shifts, meetings, extra), each the twelve monthly figures for that kind and its
     * year-to-date. The name repeats on each row so a spreadsheet reads a row on its own, where the
     * screen leans on a rowspan.
     *
     * @param  array{months: list<array<string, int>>, ytd: array<string, int>}  $breakdown
     * @return list<list<string|int>>
     */
    private function detailedRows(string $name, array $breakdown): array
    {
        return array_map(fn (string $kind): array => [
            $name,
            __("hours.dmv.detailed.kind.{$kind}"),
            ...array_column($breakdown['months'], $kind),
            $breakdown['ytd'][$kind],
        ], ['shifts', 'meetings', 'extra']);
    }

    /**
     * Detailed Committee Statistics' payload — the `viewOrgReports` gate and the numbers, shared by
     * the HTML page and its CSV twin.
     *
     * @return array<string, mixed>
     */
    private function committeeDetailedPayload(Request $request): array
    {
        $this->authorizeOrgReports($request);

        [$fiscalYear, $stats] = $this->orgStatistics($request);

        return [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'months' => $this->monthColumns($stats->months),
            'committees' => $stats->committees,
            'org' => $stats->org,
        ];
    }

    /**
     * Active Members Ranked Hours (#413, PRD #406, ADR-0022 §8) — every active and provisional
     * Member ordered by their total hours across the whole org this fiscal year, most first.
     * Underneath, the Members with no hours rows at all this year, so absence is visible rather
     * than merely missing from the list.
     *
     * Gated identically to the summary (§4). The org's per-Member numbers are not open reading,
     * so this — like every DMV-wide report — reaches only the DMV officers, Records, or super-tier.
     */
    public function rankedHours(Request $request): Response
    {
        return Inertia::render('hours/RankedHours', $this->rankedPayload($request));
    }

    /**
     * Active Members Ranked Hours as a CSV download (#414, ADR-0022 §8) — every ranked Member with
     * their scheduled, extra, and total, then the Members with no hours at all named below, from
     * the same payload and behind the same `viewOrgReports` gate.
     */
    public function rankedCsv(Request $request): StreamedResponse
    {
        $payload = $this->rankedPayload($request);

        $rows = [[
            __('hours.dmv.ranked.column.member'),
            __('hours.dmv.ranked.column.scheduled'),
            __('hours.dmv.ranked.column.extra'),
            __('hours.dmv.ranked.column.total'),
        ]];
        foreach ($payload['ranked'] as $member) {
            $rows[] = [$member['name'], $member['scheduled_hours'], $member['extra_hours'], $member['total_hours']];
        }
        // The Members with no hours rows at all — named below, as the screen names them in its
        // second card, so absence stays visible in the export too.
        $rows[] = [__('hours.dmv.ranked.no_hours')];
        foreach ($payload['noHours'] as $member) {
            $rows[] = [$member['name']];
        }

        return CsvExport::download($this->csvName(null, 'ranked-hours', "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * Active Members Ranked Hours' payload — the `viewOrgReports` gate and the numbers, shared by
     * the HTML page and its CSV twin.
     *
     * @return array<string, mixed>
     */
    private function rankedPayload(Request $request): array
    {
        $this->authorizeOrgReports($request);

        $fiscalYear = $this->fiscalYear($request);
        $months = OrgTime::fiscalYearMonths($fiscalYear);
        $totals = $this->memberFiscalTotals($months);

        $ranked = [];
        $noHours = [];
        foreach ($this->rankableMembers() as $member) {
            $sums = $totals->get($member->getKey());
            $row = ['id' => $member->getKey(), 'name' => trim("{$member->first_name} {$member->last_name}")];

            if ($sums === null) {
                $noHours[] = $row;

                continue;
            }

            $ranked[] = $row + $sums;
        }

        // Most hours first; ties broken by name so the order is stable across runs.
        usort($ranked, fn (array $a, array $b): int => $b['total_hours'] <=> $a['total_hours'] ?: strcmp($a['name'], $b['name']));

        return [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'ranked' => $ranked,
            'noHours' => $noHours,
        ];
    }

    /**
     * Members with Zero Hours (#413, PRD #406, ADR-0022 §8) — active and provisional Members
     * whose total hours this fiscal year are zero, including those with no records at all. The
     * renewal conversation that opens with "who has done nothing" reads this list.
     */
    public function zeroHours(Request $request): Response
    {
        return $this->zeroReport($request, 'hours', fn (array $sums): bool => $sums['total_hours'] === 0);
    }

    /** Members with Zero Hours as a CSV download (#414, ADR-0022 §8). */
    public function zeroHoursCsv(Request $request): StreamedResponse
    {
        return $this->zeroCsv($request, 'hours', fn (array $sums): bool => $sums['total_hours'] === 0);
    }

    /**
     * Members with Zero Shift Hours (#413, PRD #406, ADR-0022 §8) — the same roster shape, but
     * keyed on scheduled hours: active and provisional Members who worked no Shifts this fiscal
     * year, whatever extra hours they self-entered. Kept separate because the renewal question
     * "who is not turning up for shifts" is a different one.
     */
    public function zeroShiftHours(Request $request): Response
    {
        return $this->zeroReport($request, 'shift', fn (array $sums): bool => $sums['scheduled_hours'] === 0);
    }

    /** Members with Zero Shift Hours as a CSV download (#414, ADR-0022 §8). */
    public function zeroShiftHoursCsv(Request $request): StreamedResponse
    {
        return $this->zeroCsv($request, 'shift', fn (array $sums): bool => $sums['scheduled_hours'] === 0);
    }

    /**
     * Members with Zero Extra Hours (#413, PRD #406, ADR-0022 §8) — active and provisional
     * Members who self-entered no extra hours this fiscal year, whatever Shifts they worked. The
     * third of the three zero lists, kept apart so the renewal conversation has the right one.
     */
    public function zeroExtraHours(Request $request): Response
    {
        return $this->zeroReport($request, 'extra', fn (array $sums): bool => $sums['extra_hours'] === 0);
    }

    /** Members with Zero Extra Hours as a CSV download (#414, ADR-0022 §8). */
    public function zeroExtraHoursCsv(Request $request): StreamedResponse
    {
        return $this->zeroCsv($request, 'extra', fn (array $sums): bool => $sums['extra_hours'] === 0);
    }

    /**
     * The shared body of the three zero-hours reports — identical but for the variant name (for
     * the page's title and lead) and which sum the predicate tests. Every active or provisional
     * Member whose relevant fiscal-year sum is zero appears, name-ordered; a Member with no
     * records at all reads as all zeros, so they land in every list they qualify for.
     *
     * @param  callable(array{scheduled_hours: int, extra_hours: int, total_hours: int}): bool  $isZero
     */
    private function zeroReport(Request $request, string $variant, callable $isZero): Response
    {
        return Inertia::render('hours/ZeroHours', $this->zeroPayload($request, $variant, $isZero));
    }

    /**
     * The shared CSV body of the three zero-hours reports — a header and one row per Member on the
     * list, from the same payload and behind the same `viewOrgReports` gate as the screen.
     *
     * @param  callable(array{scheduled_hours: int, extra_hours: int, total_hours: int}): bool  $isZero
     */
    private function zeroCsv(Request $request, string $variant, callable $isZero): StreamedResponse
    {
        $payload = $this->zeroPayload($request, $variant, $isZero);

        $rows = [[__('hours.dmv.zero.column.member')]];
        foreach ($payload['members'] as $member) {
            $rows[] = [$member['name']];
        }

        return CsvExport::download($this->csvName(null, "zero-{$variant}-hours", "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * The shared payload of the three zero-hours reports — the `viewOrgReports` gate and the list,
     * shared by the HTML page and its CSV twin.
     *
     * @param  callable(array{scheduled_hours: int, extra_hours: int, total_hours: int}): bool  $isZero
     * @return array<string, mixed>
     */
    private function zeroPayload(Request $request, string $variant, callable $isZero): array
    {
        $this->authorizeOrgReports($request);

        $fiscalYear = $this->fiscalYear($request);
        $totals = $this->memberFiscalTotals(OrgTime::fiscalYearMonths($fiscalYear));
        $zero = ['scheduled_hours' => 0, 'extra_hours' => 0, 'total_hours' => 0];

        $members = collect($this->rankableMembers())
            ->filter(fn (Member $member): bool => $isZero($totals->get($member->getKey(), $zero)))
            ->map(fn (Member $member): array => [
                'id' => $member->getKey(),
                'name' => trim("{$member->first_name} {$member->last_name}"),
            ])
            ->values()
            ->all();

        return [
            'variant' => $variant,
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'members' => $members,
        ];
    }

    /**
     * The shared body of the two Member × twelve-month summaries — identical but for which
     * records they read (`$withMeeting` selects the meeting rows or the no-meeting ones) and
     * the page they render. Both sum `extra_hours` into one cell per bucket; scheduled hours
     * never appear here, matching legacy's two extra-hours reports.
     */
    private function summaryPayload(Request $request, Group $group, bool $withMeeting): array
    {
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $fiscalYear = $request->integer('fy') ?: OrgTime::currentFiscalYear();
        $months = OrgTime::fiscalYearMonths($fiscalYear);

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->whereIn('year_month', $months)
            ->when(
                $withMeeting,
                fn (Builder $query) => $query->where('meeting_id', '!=', HoursRecord::NO_MEETING),
                fn (Builder $query) => $query->where('meeting_id', HoursRecord::NO_MEETING),
            )
            ->with('member')
            ->get();

        return [
            'group' => $this->groupPayload($group),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->reportFiscalYears($group),
            'months' => $this->monthColumns($months),
            'members' => $records
                ->groupBy('member_id')
                ->map(function (Collection $memberRecords) use ($months): array {
                    $member = $memberRecords->first()->member;
                    $cells = collect(array_map(fn (string $yearMonth): array => [
                        'year_month' => $yearMonth,
                        'hours' => (int) $memberRecords->where('year_month', $yearMonth)->sum('extra_hours'),
                    ], $months));

                    return [
                        'id' => $member->getKey(),
                        'name' => trim("{$member->first_name} {$member->last_name}"),
                        'months' => $cells->all(),
                        'ytd' => (int) $cells->sum('hours'),
                    ];
                })
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }

    /**
     * Serialize a Member × twelve-month summary payload as CSV rows — the header, then one row per
     * Member with the twelve monthly hours in April-to-March order and the year-to-date. Shared by
     * the Extra and Meeting summaries, which differ only in which records feed the payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function summaryCsv(array $payload, Group $group, string $report): StreamedResponse
    {
        $rows = [
            [__('hours.detail.summary.column.member'), ...$this->monthHeadings($payload['months']), __('hours.detail.summary.column.ytd')],
        ];
        foreach ($payload['members'] as $member) {
            $rows[] = [$member['name'], ...array_column($member['months'], 'hours'), $member['ytd']];
        }

        return CsvExport::download($this->csvName($group->slug, $report, "fiscal-{$payload['fiscalYear']}"), $rows);
    }

    /**
     * The `YYYYMM` months a Group's month picker may land on — every month the Group itself
     * has any record in, plus the current month so the picker always has somewhere to start,
     * newest first.
     *
     * @return list<string>
     */
    private function pickableMonths(Group $group): array
    {
        return HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->distinct()
            ->pluck('year_month')
            ->push(OrgTime::now()->format('Ym'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * The Group identity every officer surface carries — id, as-authored name, and slug for
     * the links between the sibling reports.
     *
     * @return array<string, mixed>
     */
    private function groupPayload(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
        ];
    }

    /**
     * The fiscal years the report may be viewed for — every year the Group itself has any
     * record in, plus the current fiscal year so the picker always has somewhere to land,
     * newest first. Read across the Group's own records (the subtree can only add years the
     * own set already implies for the picker's purpose), so a Chair can reach a year two back.
     *
     * @return list<int>
     */
    private function reportFiscalYears(Group $group): array
    {
        return HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->distinct()
            ->pluck('year_month')
            ->map(fn (string $yearMonth): int => OrgTime::fiscalYearOf($yearMonth))
            ->push(OrgTime::currentFiscalYear())
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * The Group's records folded into one row per Member, each a twelve-month matrix in
     * April-to-March order with a year-to-date total. A Member appears iff they have hours in
     * the Group within the window; a month with nothing on file reads zero, not a gap, so the
     * twelve columns always line up. Ordered by name for a stable display.
     *
     * Scheduled and extra stay apart as well as totalled, matching the My Hours breakdown. All
     * rows for a (Member, month) are summed — the no-meeting bucket and any meeting rows — so a
     * row is the Member's whole contribution to this Group.
     *
     * @param  Collection<int, HoursRecord>  $records
     * @param  array<int, string>  $months
     * @return list<array<string, mixed>>
     */
    private function membersMatrix(Collection $records, array $months): array
    {
        return $records
            ->groupBy('member_id')
            ->map(function (Collection $memberRecords) use ($months): array {
                $member = $memberRecords->first()->member;

                $cells = collect(array_map(function (string $yearMonth) use ($memberRecords): array {
                    $forMonth = $memberRecords->where('year_month', $yearMonth);
                    $scheduled = (int) $forMonth->sum('scheduled_hours');
                    $extra = (int) $forMonth->sum('extra_hours');

                    return [
                        'year_month' => $yearMonth,
                        'scheduled_hours' => $scheduled,
                        'extra_hours' => $extra,
                        'total_hours' => $scheduled + $extra,
                    ];
                }, $months));

                return [
                    'id' => $member->getKey(),
                    'name' => trim("{$member->first_name} {$member->last_name}"),
                    'months' => $cells->all(),
                    'ytd' => [
                        'scheduled_hours' => (int) $cells->sum('scheduled_hours'),
                        'extra_hours' => (int) $cells->sum('extra_hours'),
                        'total_hours' => (int) $cells->sum('total_hours'),
                    ],
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * The fiscal years the Member may pick — every year they have any hours in, plus the
     * current fiscal year so there is always somewhere to land, newest first. Read across
     * all of the Member's records, not just the window in view, so the picker can reach a
     * year two back (§8).
     *
     * @return list<int>
     */
    private function pickableFiscalYears(int $memberId): array
    {
        return HoursRecord::query()
            ->where('member_id', $memberId)
            ->distinct()
            ->pluck('year_month')
            ->map(fn (string $yearMonth): int => OrgTime::fiscalYearOf($yearMonth))
            ->push(OrgTime::currentFiscalYear())
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * The Member's records folded into one row per Group, each a twelve-month matrix in
     * April-to-March order with a year-to-date total. A Group appears iff the Member has
     * hours in it within the window; a month with nothing on file reads zero, not a gap, so
     * the twelve columns always line up. Ordered by Group name for a stable display.
     *
     * All rows for a (Group, month) are summed — the no-meeting bucket and any meeting rows
     * together — so the total is the Member's whole contribution, matching the report a Chair
     * sees. Scheduled and extra stay apart (what the app counted vs. what they told it, §8).
     *
     * @param  Collection<int, HoursRecord>  $records
     * @param  array<int, string>  $months
     * @return list<array<string, mixed>>
     */
    private function groupsMatrix(Collection $records, array $months): array
    {
        return $records
            ->groupBy('group_id')
            ->map(function (Collection $groupRecords) use ($months): array {
                $group = $groupRecords->first()->group;

                $cells = collect(array_map(function (string $yearMonth) use ($groupRecords): array {
                    $forMonth = $groupRecords->where('year_month', $yearMonth);
                    $scheduled = (int) $forMonth->sum('scheduled_hours');
                    $extra = (int) $forMonth->sum('extra_hours');

                    return [
                        'year_month' => $yearMonth,
                        'scheduled_hours' => $scheduled,
                        'extra_hours' => $extra,
                        'total_hours' => $scheduled + $extra,
                    ];
                }, $months));

                return [
                    'id' => $group->getKey(),
                    'name' => $group->name,
                    'months' => $cells->all(),
                    'ytd' => [
                        'scheduled_hours' => (int) $cells->sum('scheduled_hours'),
                        'extra_hours' => (int) $cells->sum('extra_hours'),
                        'total_hours' => (int) $cells->sum('total_hours'),
                    ],
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * The DMV-wide report gate (ADR-0022 §4): a Chair, Secretary, or Statistician of the DMV
     * root Group, the Records stewardship, or the super-tier. Refused with a 403 for everyone
     * else — the same outcome the CSV export (#414) will give, so the export is never a way
     * around the gate.
     */
    private function authorizeOrgReports(Request $request): void
    {
        abort_unless($request->user()->can('viewOrgReports', HoursRecord::class), 403);
    }

    /**
     * The fiscal year a report is viewed for — the `?fy=` query param, defaulting to the
     * current fiscal year on the org wall clock so an unqualified link lands on this year.
     */
    private function fiscalYear(Request $request): int
    {
        return $request->integer('fy') ?: OrgTime::currentFiscalYear();
    }

    /**
     * The committee-statistics matrix for the DMV root and the fiscal year in view — shared by
     * the Summary and Detailed reports, which shape the same object differently. The root is
     * resolved by its single reserved slug; a 404 if the org is unseeded.
     *
     * @return array{0: int, 1: CommitteeHoursStatistics}
     */
    private function orgStatistics(Request $request): array
    {
        $fiscalYear = $this->fiscalYear($request);
        $root = Group::where('slug', Group::ROOT_SLUG)->firstOrFail();

        return [$fiscalYear, CommitteeHoursStatistics::for($root, $fiscalYear)];
    }

    /**
     * The twelve month columns as the page reads them — a `YYYYMM` bucket paired with its
     * first-of-month ISO date, for a localized month name that never slides a day.
     *
     * @param  array<int, string>  $months
     * @return list<array{year_month: string, month: string}>
     */
    private function monthColumns(array $months): array
    {
        return array_map(fn (string $yearMonth): array => [
            'year_month' => $yearMonth,
            'month' => $this->monthStart($yearMonth),
        ], $months);
    }

    /**
     * The twelve month columns as CSV headings — each bucket's first-of-month date, localized to
     * the request locale so the export's chrome is translated like the screen's (ADR-0022 §8).
     * The day is always the first, so the abbreviated month never slides into the one before.
     *
     * @param  list<array{year_month: string, month: string}>  $months
     * @return list<string>
     */
    private function monthHeadings(array $months): array
    {
        return array_map(
            fn (array $column): string => CarbonImmutable::parse($column['month'])->locale(app()->getLocale())->isoFormat('MMM YYYY'),
            $months,
        );
    }

    /**
     * A stable, ASCII-safe CSV filename — the report slug and a suffix (the fiscal year, the
     * month, or the picked Member), prefixed with the Group slug where the report is scoped to
     * one. Names render as-authored inside the file; the filename stays a slug so it survives any
     * spreadsheet and any filesystem.
     */
    private function csvName(?string $groupSlug, string $report, string $suffix): string
    {
        return ($groupSlug !== null ? "{$groupSlug}-" : '')."{$report}-{$suffix}.csv";
    }

    /**
     * One org-wide summary row — the twelve buckets of a named part (`meetings`, `extra`, or
     * `total`) of the root's subtree breakdown, and its year-to-date.
     *
     * @param  array<string, mixed>  $org
     * @return array{months: list<int>, ytd: int}
     */
    private function orgRow(array $org, string $part): array
    {
        return [
            'months' => array_column($org['months'], $part),
            'ytd' => $org['ytd'][$part],
        ];
    }

    /**
     * The fiscal years the org-wide reports may be viewed for — every year any record exists
     * in across the whole org, plus the current fiscal year so the picker always has somewhere
     * to land, newest first.
     *
     * @return list<int>
     */
    private function orgFiscalYears(): array
    {
        return HoursRecord::query()
            ->distinct()
            ->pluck('year_month')
            ->map(fn (string $yearMonth): int => OrgTime::fiscalYearOf($yearMonth))
            ->push(OrgTime::currentFiscalYear())
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * The roster the ranked and zero-hours reports draw from: every active and provisional
     * Member (ADR-0022 §8 story 53), name-ordered so the lists are stable across runs. The
     * departed and the not-yet-activated are out of scope — a renewal conversation is about
     * present Members.
     *
     * @return Collection<int, Member>
     */
    private function rankableMembers(): Collection
    {
        return Member::query()
            ->whereIn('category', [Category::Active, Category::Provisional])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Each Member's summed hours across the whole org for the given months, keyed by Member id.
     * A Member with no records this fiscal year is simply absent from the map, so callers can
     * tell "no rows at all" from "rows that sum to zero".
     *
     * @param  array<int, string>  $months
     * @return Collection<int, array{scheduled_hours: int, extra_hours: int, total_hours: int}>
     */
    private function memberFiscalTotals(array $months): Collection
    {
        return HoursRecord::query()
            ->whereIn('year_month', $months)
            ->get()
            ->groupBy('member_id')
            ->map(fn (Collection $records): array => [
                'scheduled_hours' => (int) $records->sum('scheduled_hours'),
                'extra_hours' => (int) $records->sum('extra_hours'),
                'total_hours' => (int) $records->sum('total_hours'),
            ]);
    }

    /**
     * The first day of a `YYYYMM` bucket as an ISO date, on the org wall clock — the value
     * the page formats into a localized month name, resolved in the org zone so the month
     * never slips a day across the UTC boundary.
     */
    private function monthStart(string $yearMonth): string
    {
        return CarbonImmutable::createFromFormat('Ym', $yearMonth, config('app.org_timezone'))
            ->startOfMonth()
            ->toDateString();
    }
}
