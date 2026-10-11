<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\ExhibitionRevenue;
use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures behind Tour Summary and Tour Detail (#800, ADR-0032 §12) for one Group over a run of
 * `YYYYMM` months.
 *
 * - **Tours** count docent-tours, one per Sign-up, as the old report does.
 * - **Visitors** on a group tour are the Booking's expected visitors; on a scheduled tour, the
 *   Sign-up's recorded `visitor_count`.
 * - **Earned** is {@see Booking::earned()}: the Statistician's correction where one is set.
 * - **Scheduled tours** are the Sign-ups on the Group's Shifts that carry no Booking.
 *
 * A Shift falls in the month of its `ends_at` on the org clock, as hours do
 * ({@see HoursRecord::recalculateScheduled()}). Money is summed in cents and returned as
 * two-decimal strings, like the rate columns.
 */
class TourStatistics
{
    /**
     * The period a report reads, from its query: the fiscal year to date for `?fy=YYYY` (a year
     * still to come reads as the current one), else the month `?month=YYYYMM`, else the current
     * month. `month` is the bucket's first day on the org clock, for the page to spell.
     *
     * @return array{kind: 'month'|'year', fiscal_year: int, year_month: string|null, month: string|null}
     */
    public static function period(string $fy, string $month): array
    {
        if (preg_match('/^\d{4}$/', $fy) === 1) {
            return ['kind' => 'year', 'fiscal_year' => min((int) $fy, OrgTime::currentFiscalYear()), 'year_month' => null, 'month' => null];
        }

        $yearMonth = preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $month) === 1 ? $month : OrgTime::now()->format('Ym');

        return ['kind' => 'month', 'fiscal_year' => OrgTime::fiscalYearOf($yearMonth), 'year_month' => $yearMonth, 'month' => OrgTime::monthStart($yearMonth)];
    }

    /**
     * The `YYYYMM` buckets a period covers, ascending: its month, or its fiscal year's months up
     * to the current one.
     *
     * @param  array{kind: 'month'|'year', fiscal_year: int, year_month: string|null, month: string|null}  $period
     * @return list<string>
     */
    public static function monthsOf(array $period): array
    {
        if ($period['kind'] === 'month') {
            return [$period['year_month']];
        }

        $current = OrgTime::now()->format('Ym');

        return array_values(array_filter(OrgTime::fiscalYearMonths($period['fiscal_year']), fn (string $ym): bool => $ym <= $current));
    }

    /**
     * What the period picker offers: every month the Group has a Booking in, plus the current
     * one, newest first; and the fiscal years those months fall in.
     *
     * @return array{months: list<array{year_month: string, month: string}>, fiscalYears: list<int>}
     */
    public static function picker(Group $group): array
    {
        $zone = config('app.org_timezone');

        $months = Booking::query()
            ->where('group_id', $group->id)
            ->with('shift')
            ->get()
            ->map(fn (Booking $booking): string => $booking->shift->ends_at->setTimezone($zone)->format('Ym'))
            ->push(OrgTime::now()->format('Ym'))
            ->unique()
            ->sortDesc()
            ->values();

        return [
            'months' => $months->map(fn (string $ym): array => ['year_month' => $ym, 'month' => OrgTime::monthStart($ym)])->all(),
            'fiscalYears' => $months->map(fn (string $ym): int => OrgTime::fiscalYearOf($ym))->unique()->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $months  ascending, contiguous `YYYYMM` buckets
     * @return array<string, mixed>
     */
    public static function for(Group $group, array $months): array
    {
        $zone = config('app.org_timezone');
        $from = CarbonImmutable::createFromFormat('YmdHis', $months[0].'01000000', $zone)->utc();
        $until = CarbonImmutable::createFromFormat('YmdHis', end($months).'01000000', $zone)->addMonth()->utc();
        $inPeriod = fn (Builder $shift) => $shift->where('ends_at', '>=', $from)->where('ends_at', '<', $until);

        $bookings = Booking::query()
            ->where('group_id', $group->id)
            ->whereHas('shift', $inPeriod)
            ->with(['shift.signUps', 'bookingType', 'tour'])
            ->get();

        $scheduled = SignUp::query()
            ->whereHas('shift', fn (Builder $shift) => $inPeriod($shift)
                ->whereHas('schedule', fn (Builder $schedule) => $schedule->where('group_id', $group->id))
                ->whereDoesntHave('booking'))
            ->get();

        $exhibitionCents = ExhibitionRevenue::query()
            ->where('group_id', $group->id)
            ->whereIn('year_month', $months)
            ->get()
            ->sum(fn (ExhibitionRevenue $row): int => self::toCents($row->amount));

        // Every active type, and any retired one with a Booking in the period, in the Group's order.
        $byType = $bookings->groupBy('booking_type_id');
        $types = BookingType::query()
            ->where('group_id', $group->id)
            ->ordered()
            ->get()
            ->filter(fn (BookingType $type): bool => $type->active || $byType->has($type->id));

        $groupTours = self::figures($bookings);
        $scheduledTours = ['tours' => $scheduled->count(), 'visitors' => (int) $scheduled->sum('visitor_count')];

        return [
            'types' => $types->map(fn (BookingType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                ...self::money(self::figures($byType->get($type->id, collect()))),
            ])->values()->all(),
            'detail' => $types
                ->filter(fn (BookingType $type): bool => $byType->has($type->id))
                ->map(fn (BookingType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'tours' => $byType->get($type->id)
                        ->groupBy('tour_id')
                        ->map(fn (Collection $tourBookings): array => [
                            'id' => $tourBookings->first()->tour->id,
                            'name' => $tourBookings->first()->tour->name,
                            'sort_order' => $tourBookings->first()->tour->sort_order,
                            ...self::money(self::figures($tourBookings)),
                        ])
                        ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                        ->map(fn (array $row): array => array_diff_key($row, ['sort_order' => true]))
                        ->values()
                        ->all(),
                    'total' => self::money(self::figures($byType->get($type->id))),
                ])->values()->all(),
            'group_tours' => self::money($groupTours),
            'scheduled' => $scheduledTours,
            'exhibition_revenue' => self::format($exhibitionCents),
            'grand_total' => self::money([
                'tours' => $groupTours['tours'] + $scheduledTours['tours'],
                'visitors' => $groupTours['visitors'] + $scheduledTours['visitors'],
                'earned' => $groupTours['earned'] + $exhibitionCents,
            ]),
        ];
    }

    /**
     * Tours, visitors and Earned (in cents) over a set of Bookings.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array{tours: int, visitors: int, earned: int}
     */
    private static function figures(Collection $bookings): array
    {
        return [
            'tours' => $bookings->sum(fn (Booking $booking): int => $booking->shift->signUps->count()),
            'visitors' => (int) $bookings->sum('visitors'),
            'earned' => $bookings->sum(fn (Booking $booking): int => self::toCents($booking->earned())),
        ];
    }

    /**
     * @param  array{tours: int, visitors: int, earned: int}  $figures
     * @return array{tours: int, visitors: int, earned: string}
     */
    private static function money(array $figures): array
    {
        return [...$figures, 'earned' => self::format($figures['earned'])];
    }

    private static function toCents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
