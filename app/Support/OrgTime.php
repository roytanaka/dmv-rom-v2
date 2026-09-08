<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * The one place the organization's wall clock is converted to storage time.
 *
 * Datetimes are stored in UTC (`config('app.timezone')`) but entered and read in
 * the museum's local time (`config('app.org_timezone')`) — a meeting "at 11am" is
 * 11am at the ROM for every viewer, wherever their device is. The two zones differ
 * by four or five hours depending on the season, so a datetime arriving from a
 * browser without an offset — which is all `<input type="datetime-local">` ever
 * sends — must be pinned to the org zone before it is stored, or it lands hours off.
 *
 * The output side is symmetric but lives on the client: datetimes cross the wire as
 * UTC instants and are formatted with `timeZone: page.props.timezone`.
 */
class OrgTime
{
    /**
     * Read a datetime the user entered on the org's wall clock and return the
     * equivalent UTC instant as `Y-m-d H:i:s`, ready for the validator and the
     * `datetime` cast.
     *
     * A value carrying its own offset (`…Z`, `…-04:00`) is already unambiguous and
     * is converted, not reinterpreted. A non-string or unparseable value is returned
     * untouched so the `date` validation rule — not an exception — rejects it.
     */
    public static function toUtc(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value, config('app.org_timezone'))
                ->utc()
                ->toDateTimeString();
        } catch (InvalidFormatException) {
            return $value;
        }
    }

    /**
     * The current instant on the organization's wall clock. The one place "now" is read
     * for month-bucket work, so a Member's entry window is decided against the museum's day
     * — not the server's UTC day, which after 8pm is already tomorrow.
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.org_timezone'));
    }

    /**
     * The fiscal year a `YYYYMM` bucket falls in (ADR-0022 §8). The fiscal year runs 1 April
     * to 31 March and is named for the year it *ends* in — so "Fiscal 2026" runs 2025-04
     * through 2026-03. A month in April or later opens the fiscal year named for the next
     * March; January through March close the one named for this year. This is the single
     * definition the My Hours destination and every report share, so the boundary is decided
     * once rather than in each caller.
     */
    public static function fiscalYearOf(string $yearMonth): int
    {
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 4, 2);

        return $month >= 4 ? $year + 1 : $year;
    }

    /**
     * The current fiscal year, read on the org wall clock (ADR-0022 §8) — so a My Hours view
     * opened late on 31 March is still the closing fiscal year, not the next one the server's
     * UTC day has already rolled into.
     */
    public static function currentFiscalYear(): int
    {
        return self::fiscalYearOf(self::now()->format('Ym'));
    }

    /**
     * A fiscal year expanded into its twelve `YYYYMM` buckets in reporting order — April
     * first through the following March (ADR-0022 §8). The one place a fiscal year becomes
     * its month columns, shared by My Hours and the fiscal-year reports so the twelve buckets
     * are ordered identically everywhere.
     *
     * @return array<int, string>
     */
    public static function fiscalYearMonths(int $fiscalYear): array
    {
        $april = CarbonImmutable::create($fiscalYear - 1, 4, 1, 0, 0, 0, config('app.org_timezone'));

        return array_map(
            fn (int $offset): string => $april->addMonths($offset)->format('Ym'),
            range(0, 11),
        );
    }

    /**
     * The two `YYYYMM` month buckets a Member may enter extra hours in (ADR-0022 §2): the
     * current month and the one before it, on the org wall clock, newest first. This is the
     * reporting window the department agreed — nothing older is reachable — and the single
     * source the entry form offers and the Form Request validates against.
     *
     * @return array<int, string>
     */
    public static function entryMonths(): array
    {
        $now = self::now();

        return [
            $now->format('Ym'),
            $now->subMonthWithoutOverflow()->format('Ym'),
        ];
    }
}
