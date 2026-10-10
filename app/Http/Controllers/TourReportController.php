<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExhibitionRevenueRequest;
use App\Models\Booking;
use App\Models\ExhibitionRevenue;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Support\CsvExport;
use App\Support\OrgTime;
use App\Support\TourStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tour Summary and Tour Detail (#800, ADR-0032 §12) — a Group's group tours totalled by booking
 * type (and, in the detail, by Tour), then the scheduled tours and exhibition revenue in a grand
 * total. For one month (`?month=YYYYMM`, the current month by default) or the fiscal year to date
 * (`?fy=YYYY`). Print pages with CSV siblings, behind the hours-report gate (`viewReports`: a Chair
 * or Statistician of the Group or an ancestor, and super-tier). The figures are
 * {@see TourStatistics}.
 */
class TourReportController extends Controller
{
    public function summary(Request $request, Group $group): Response
    {
        return Inertia::render('groups/TourSummary', $this->payload($request, $group));
    }

    public function detail(Request $request, Group $group): Response
    {
        return Inertia::render('groups/TourDetail', $this->payload($request, $group));
    }

    /**
     * Tour Summary as CSV: a row per booking type, the group-tour subtotal, the scheduled tours,
     * the exhibition revenue and the grand total.
     */
    public function summaryCsv(Request $request, Group $group): StreamedResponse
    {
        $payload = $this->payload($request, $group);

        $rows = [[__('hours.tours.column.type'), __('hours.tours.column.tours'), __('hours.tours.column.visitors'), __('hours.tours.column.earned')]];
        foreach ($payload['types'] as $type) {
            $rows[] = [$type['name'], $type['tours'], $type['visitors'], $type['earned']];
        }

        return CsvExport::download($this->csvName($group, 'tour-summary', $payload['period']), [...$rows, ...$this->totalRows($payload, 1)]);
    }

    /**
     * Tour Detail as CSV: a row per booking type and Tour, each type's subtotal, then the same
     * closing rows as the summary.
     */
    public function detailCsv(Request $request, Group $group): StreamedResponse
    {
        $payload = $this->payload($request, $group);

        $rows = [[__('hours.tours.column.type'), __('hours.tours.column.tour'), __('hours.tours.column.tours'), __('hours.tours.column.visitors'), __('hours.tours.column.earned')]];
        foreach ($payload['detail'] as $type) {
            foreach ($type['tours'] as $tour) {
                $rows[] = [$type['name'], $tour['name'], $tour['tours'], $tour['visitors'], $tour['earned']];
            }
            $rows[] = [$type['name'], __('hours.tours.row.type_total'), $type['total']['tours'], $type['total']['visitors'], $type['total']['earned']];
        }

        return CsvExport::download($this->csvName($group, 'tour-detail', $payload['period']), [...$rows, ...$this->totalRows($payload, 2)]);
    }

    /**
     * Set or clear (null) the Group's exhibition revenue for a month, from the Tour Summary.
     */
    public function updateExhibitionRevenue(UpdateExhibitionRevenueRequest $request, Group $group): RedirectResponse
    {
        $amount = $request->validated('amount');

        ExhibitionRevenue::enter($group, $request->validated('year_month'), $amount === null ? null : (string) $amount);

        return back();
    }

    /**
     * The gate, the period and the figures, shared by both pages and their CSVs.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request, Group $group): array
    {
        abort_unless($group->has_bookings, 404);
        abort_unless($request->user()->can('viewReports', [HoursRecord::class, $group]), 403);

        $current = OrgTime::now()->format('Ym');
        $fy = $request->string('fy')->toString();
        $month = $request->string('month')->toString();

        if (preg_match('/^\d{4}$/', $fy) === 1) {
            // The fiscal year to date: its months up to the current one. A year still to come
            // reads as the current one.
            $fiscalYear = min((int) $fy, OrgTime::currentFiscalYear());
            $months = array_values(array_filter(OrgTime::fiscalYearMonths($fiscalYear), fn (string $ym): bool => $ym <= $current));
            $period = ['kind' => 'year', 'fiscal_year' => $fiscalYear, 'year_month' => null, 'month' => null];
        } else {
            $yearMonth = preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $month) === 1 ? $month : $current;
            $months = [$yearMonth];
            $period = ['kind' => 'month', 'fiscal_year' => OrgTime::fiscalYearOf($yearMonth), 'year_month' => $yearMonth, 'month' => $this->monthStart($yearMonth)];
        }

        $pickable = $this->pickableMonths($group, $current);
        $entered = $period['kind'] === 'month'
            ? ExhibitionRevenue::query()->where('group_id', $group->id)->where('year_month', $period['year_month'])->value('amount')
            : null;

        return [
            'group' => ['id' => $group->id, 'name' => $group->name, 'slug' => $group->slug, 'has_bookings' => true],
            'period' => $period,
            'months' => array_map(fn (string $ym): array => ['year_month' => $ym, 'month' => $this->monthStart($ym)], $pickable),
            'fiscalYears' => collect($pickable)->map(fn (string $ym): int => OrgTime::fiscalYearOf($ym))->unique()->values()->all(),
            ...TourStatistics::for($group, $months),
            'exhibition' => [
                'can_enter' => $request->user()->can('enterExhibitionRevenue', [Booking::class, $group]),
                'amount' => $entered,
            ],
        ];
    }

    /**
     * The closing CSV rows both reports share: group tours, scheduled tours, exhibition revenue
     * and the grand total, padded by $labelColumns label cells.
     *
     * @param  array<string, mixed>  $payload
     * @return list<list<string|int>>
     */
    private function totalRows(array $payload, int $labelColumns): array
    {
        $pad = array_fill(0, $labelColumns - 1, '');

        return [
            [__('hours.tours.row.group_tours'), ...$pad, $payload['group_tours']['tours'], $payload['group_tours']['visitors'], $payload['group_tours']['earned']],
            [__('hours.tours.row.scheduled'), ...$pad, $payload['scheduled']['tours'], $payload['scheduled']['visitors'], ''],
            [__('hours.tours.row.exhibition'), ...$pad, '', '', $payload['exhibition_revenue']],
            [__('hours.tours.row.grand_total'), ...$pad, $payload['grand_total']['tours'], $payload['grand_total']['visitors'], $payload['grand_total']['earned']],
        ];
    }

    /**
     * The `YYYYMM` months the picker offers: every month the Group has a Booking in, plus the
     * current one, newest first.
     *
     * @return list<string>
     */
    private function pickableMonths(Group $group, string $current): array
    {
        $zone = config('app.org_timezone');

        return Booking::query()
            ->where('group_id', $group->id)
            ->with('shift')
            ->get()
            ->map(fn (Booking $booking): string => $booking->shift->ends_at->setTimezone($zone)->format('Ym'))
            ->push($current)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * A stable ASCII filename: the Group slug, the report, and the month or fiscal year.
     *
     * @param  array<string, mixed>  $period
     */
    private function csvName(Group $group, string $report, array $period): string
    {
        $suffix = $period['kind'] === 'month' ? $period['year_month'] : "fiscal-{$period['fiscal_year']}";

        return "{$group->slug}-{$report}-{$suffix}.csv";
    }

    /**
     * The first day of a `YYYYMM` bucket as an ISO date on the org clock.
     */
    private function monthStart(string $yearMonth): string
    {
        return CarbonImmutable::createFromFormat('YmdHis', $yearMonth.'01000000', config('app.org_timezone'))->toDateString();
    }
}
