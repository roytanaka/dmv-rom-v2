<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Http\Requests\RecalculateHoursRequest;
use App\Http\Requests\StoreHoursRecordRequest;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\CommitteeHoursStatistics;
use App\Support\GroupHoursMatrix;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

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
     * Add extra hours for the authenticated Member on a Group, in one of the two open
     * months. The write is additive and floors at zero; a blank or zero entry (or a
     * correction the floor swallows) writes nothing at all. Every real write appends an
     * Hours adjustment in the same transaction ({@see HoursRecord::enterExtra()}).
     */
    public function store(StoreHoursRecordRequest $request, Group $group): RedirectResponse
    {
        $actor = $request->user();

        HoursRecord::enterExtra(
            $actor,
            $group,
            $request->validated('year_month'),
            (int) $request->validated('hours'),
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
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $fiscalYear = $request->integer('fy') ?: OrgTime::currentFiscalYear();
        $months = OrgTime::fiscalYearMonths($fiscalYear);
        $matrix = GroupHoursMatrix::for($group, $fiscalYear);

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->whereIn('year_month', $months)
            ->with('member')
            ->get();

        return Inertia::render('groups/HoursReport', [
            'group' => $this->groupPayload($group),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->reportFiscalYears($group),
            'months' => array_map(fn (string $yearMonth): array => [
                'year_month' => $yearMonth,
                'month' => $this->monthStart($yearMonth),
            ], $months),
            'members' => $this->membersMatrix($records, $months),
            'totals' => [
                'own' => ['months' => $matrix->own, 'ytd' => $matrix->ownYtd],
                'subtree' => ['months' => $matrix->subtree, 'ytd' => $matrix->subtreeYtd],
            ],
        ]);
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
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $pickable = $this->pickableMonths($group);
        $yearMonth = $request->string('month')->toString() ?: OrgTime::now()->format('Ym');

        $records = HoursRecord::query()
            ->where('group_id', $group->getKey())
            ->where('year_month', $yearMonth)
            ->with('member')
            ->get();

        return Inertia::render('groups/HoursMonth', [
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
        ]);
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

        return Inertia::render('groups/HoursMemberHistory', [
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
        ]);
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
        return $this->summary($request, $group, 'groups/HoursExtraSummary', withMeeting: false);
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
        return $this->summary($request, $group, 'groups/HoursMeetingSummary', withMeeting: true);
    }

    /**
     * Summary Committee Statistics (#413, PRD #406, ADR-0022 §8) — the first of the six
     * DMV-wide reports, and the single output the whole Hours feature exists to produce.
     * Groups × twelve months of **scheduled hours**, then org-wide rows underneath for meeting
     * hours, extra hours, and the grand total.
     *
     * The scheduled section lists only the committees that run scheduling ({@see
     * Group::$has_scheduling}) — a Group with no schedule can never carry a number there, so an
     * empty row would be noise. The three org-wide rows read the DMV root's whole subtree (the
     * complete org total) from {@see CommitteeHoursStatistics}.
     *
     * Gated to the DMV root Group's Chair, Secretary, or Statistician, the Records stewardship,
     * or the super-tier (§4) via `viewOrgReports` — an ordinary Member reaches none of the six.
     */
    public function committeeSummary(Request $request): Response
    {
        $this->authorizeOrgReports($request);

        [$fiscalYear, $stats] = $this->orgStatistics($request);

        return Inertia::render('hours/CommitteeSummary', [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'months' => $this->monthColumns($stats->months),
            // Only committees that run scheduling; their scheduled hours across the subtree.
            'scheduled' => collect($stats->committees)
                ->where('has_scheduling', true)
                ->map(fn (array $committee): array => [
                    'id' => $committee['id'],
                    'name' => $committee['name'],
                    'months' => array_column($committee['months'], 'shifts'),
                    'ytd' => $committee['ytd']['shifts'],
                ])
                ->values()
                ->all(),
            'orgRows' => [
                'meetings' => $this->orgRow($stats->org, 'meetings'),
                'extra' => $this->orgRow($stats->org, 'extra'),
                'total' => $this->orgRow($stats->org, 'total'),
            ],
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
        $this->authorizeOrgReports($request);

        [$fiscalYear, $stats] = $this->orgStatistics($request);

        return Inertia::render('hours/CommitteeDetailed', [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'months' => $this->monthColumns($stats->months),
            'committees' => $stats->committees,
            'org' => $stats->org,
        ]);
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

        return Inertia::render('hours/RankedHours', [
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'ranked' => $ranked,
            'noHours' => $noHours,
        ]);
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

    /**
     * Members with Zero Extra Hours (#413, PRD #406, ADR-0022 §8) — active and provisional
     * Members who self-entered no extra hours this fiscal year, whatever Shifts they worked. The
     * third of the three zero lists, kept apart so the renewal conversation has the right one.
     */
    public function zeroExtraHours(Request $request): Response
    {
        return $this->zeroReport($request, 'extra', fn (array $sums): bool => $sums['extra_hours'] === 0);
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

        return Inertia::render('hours/ZeroHours', [
            'variant' => $variant,
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->orgFiscalYears(),
            'members' => $members,
        ]);
    }

    /**
     * The shared body of the two Member × twelve-month summaries — identical but for which
     * records they read (`$withMeeting` selects the meeting rows or the no-meeting ones) and
     * the page they render. Both sum `extra_hours` into one cell per bucket; scheduled hours
     * never appear here, matching legacy's two extra-hours reports.
     */
    private function summary(Request $request, Group $group, string $component, bool $withMeeting): Response
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

        return Inertia::render($component, [
            'group' => $this->groupPayload($group),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $this->reportFiscalYears($group),
            'months' => array_map(fn (string $yearMonth): array => [
                'year_month' => $yearMonth,
                'month' => $this->monthStart($yearMonth),
            ], $months),
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
        ]);
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
