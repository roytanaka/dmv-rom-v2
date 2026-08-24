<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecalculateHoursRequest;
use App\Http\Requests\StoreHoursRecordRequest;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Support\GroupHoursMatrix;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
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
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
            ],
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
