<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExhibitionRevenueRequest;
use App\Models\Booking;
use App\Models\ExhibitionRevenue;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Support\CsvExport;
use App\Support\TourStatistics;
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
        ExhibitionRevenue::enter($group, $request->validated('year_month'), $request->validatedMoney('amount'));

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

        $period = TourStatistics::period($request->string('fy')->toString(), $request->string('month')->toString());

        return [
            'group' => ['id' => $group->id, 'name' => $group->name, 'slug' => $group->slug, 'has_bookings' => true],
            'period' => $period,
            ...TourStatistics::picker($group),
            ...TourStatistics::for($group, TourStatistics::monthsOf($period)),
            'exhibition' => [
                'can_enter' => $request->user()->can('enterExhibitionRevenue', [Booking::class, $group]),
                'amount' => $period['kind'] === 'month' ? ExhibitionRevenue::amountFor($group, $period['year_month']) : null,
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
     * A stable ASCII filename: the Group slug, the report, and the month or fiscal year.
     *
     * @param  array<string, mixed>  $period
     */
    private function csvName(Group $group, string $report, array $period): string
    {
        $suffix = $period['kind'] === 'month' ? $period['year_month'] : "fiscal-{$period['fiscal_year']}";

        return "{$group->slug}-{$report}-{$suffix}.csv";
    }
}
