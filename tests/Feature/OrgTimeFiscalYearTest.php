<?php

use App\Support\OrgTime;

/*
 * The fiscal-year concern (#409, PRD #406, ADR-0022 §8) lives in one place — OrgTime —
 * so the My Hours destination and the report tickets that follow share one definition.
 * The fiscal year runs 1 April to 31 March and is named for the year it ends in:
 * "Fiscal 2026" runs 2025-04 through 2026-03.
 */

it('maps a month to the fiscal year it falls in', function () {
    // March belongs to the fiscal year ending that March; April to the next one.
    expect(OrgTime::fiscalYearOf('202603'))->toBe(2026)
        ->and(OrgTime::fiscalYearOf('202604'))->toBe(2027)
        // The turn of the calendar year stays inside the same fiscal year.
        ->and(OrgTime::fiscalYearOf('202512'))->toBe(2026)
        ->and(OrgTime::fiscalYearOf('202601'))->toBe(2026)
        // April opens the fiscal year named for the following March.
        ->and(OrgTime::fiscalYearOf('202504'))->toBe(2026);
});

it('expands a fiscal year into its twelve ordered month buckets, April to March', function () {
    expect(OrgTime::fiscalYearMonths(2026))->toBe([
        '202504', '202505', '202506', '202507', '202508', '202509',
        '202510', '202511', '202512', '202601', '202602', '202603',
    ]);
});

it('reads the current fiscal year on the org wall clock', function () {
    // Frozen inside Fiscal 2026 (a March day) — the boundary the mapping turns on.
    $this->travelTo('2026-03-15 12:00:00');
    expect(OrgTime::currentFiscalYear())->toBe(2026);

    // One month on is April — the first month of Fiscal 2027.
    $this->travelTo('2026-04-01 12:00:00');
    expect(OrgTime::currentFiscalYear())->toBe(2027);
});
