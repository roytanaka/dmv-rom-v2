/**
 * Unit tests for the Agenda's day-grouping (#355, ADR-0021 §5).
 *
 * Runs on Node's built-in test runner with native TypeScript support — no test
 * framework dependency (`node --test resources/js/scheduling/agenda.test.ts`, or
 * `pnpm test:unit`). Prior art: `resources/js/chrome/railFilter.test.ts`.
 *
 * The load-bearing case is the timezone one: a Shift's day is the day on the org wall
 * clock, not the viewer's zone and not UTC. A late-evening Toronto Shift stored as the
 * next UTC day must still group under the Toronto day it happens on — otherwise the
 * Agenda splits one evening across two headings. Grouping is a pure function of the
 * delivered instants and the org timezone; it issues no network request.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildMonthGrid, groupShiftsByDay, monthsInRange, orgDayKey } from './agenda.ts';

const TORONTO = 'America/Toronto';

test('orgDayKey resolves the calendar day on the org wall clock', () => {
    // 01:00 UTC on Aug 6 is 21:00 EDT on Aug 5 in Toronto (UTC-4 in summer).
    assert.equal(orgDayKey('2026-08-06T01:00:00Z', TORONTO), '2026-08-05');
    assert.equal(orgDayKey('2026-08-05T14:00:00Z', TORONTO), '2026-08-05');
});

test('groups Shifts sharing an org-wall-clock day under one heading', () => {
    const groups = groupShiftsByDay(
        [
            { id: 1, starts_at: '2026-08-05T13:00:00Z' },
            { id: 2, starts_at: '2026-08-05T18:00:00Z' },
        ],
        TORONTO,
    );

    assert.equal(groups.length, 1);
    assert.equal(groups[0].date, '2026-08-05');
    assert.deepEqual(
        groups[0].shifts.map((s) => s.id),
        [1, 2],
    );
});

test('splits Shifts across days and orders the days ascending', () => {
    const groups = groupShiftsByDay(
        [
            { id: 1, starts_at: '2026-08-07T13:00:00Z' },
            { id: 2, starts_at: '2026-08-05T13:00:00Z' },
        ],
        TORONTO,
    );

    assert.deepEqual(
        groups.map((g) => g.date),
        ['2026-08-05', '2026-08-07'],
    );
});

test('buckets a late-evening Toronto Shift under the day it happens on, not the UTC day', () => {
    // 02:00 UTC on Aug 6 is 22:00 EDT on Aug 5 — the same Agenda day as an afternoon one.
    const groups = groupShiftsByDay(
        [
            { id: 1, starts_at: '2026-08-05T18:00:00Z' },
            { id: 2, starts_at: '2026-08-06T02:00:00Z' },
        ],
        TORONTO,
    );

    assert.equal(groups.length, 1);
    assert.equal(groups[0].date, '2026-08-05');
});

test('returns no groups for no Shifts', () => {
    assert.deepEqual(groupShiftsByDay([], TORONTO), []);
});

// ── Month-grid construction (#360, ADR-0021 §7) — the Calendar's shared pure layout ──

test('buildMonthGrid lays a month out as whole weeks of seven with leading and trailing blanks', () => {
    // Aug 2026: the 1st is a Saturday, so six leading blanks precede it under a Sunday
    // week start; 6 + 31 = 37 cells round up to six weeks (42), leaving five trailing blanks.
    const grid = buildMonthGrid([], 2026, 8);

    assert.equal(grid.year, 2026);
    assert.equal(grid.month, 8);
    assert.equal(grid.weeks.length, 6);
    assert.ok(
        grid.weeks.every((week) => week.length === 7),
        'every week is seven cells',
    );

    const flat = grid.weeks.flat();
    assert.deepEqual(
        flat.slice(0, 6).map((cell) => cell.date),
        [null, null, null, null, null, null],
        'six leading blanks before Saturday the 1st',
    );
    assert.equal(flat[6].date, '2026-08-01');
    assert.equal(flat[36].date, '2026-08-31');
    assert.deepEqual(
        flat.slice(37).map((cell) => cell.date),
        [null, null, null, null, null],
        'five trailing blanks fill the last week',
    );
});

test('buildMonthGrid pads a month that starts on the week start to exact weeks', () => {
    // Feb 2026: the 1st is a Sunday and the month has 28 days — four clean weeks, no blanks.
    const grid = buildMonthGrid([], 2026, 2);

    assert.equal(grid.weeks.length, 4);
    assert.equal(grid.weeks[0][0].date, '2026-02-01');
    assert.equal(grid.weeks[3][6].date, '2026-02-28');
});

test('buildMonthGrid drops each day-group into its own cell and leaves other days empty', () => {
    const grid = buildMonthGrid(
        [
            { date: '2026-08-05', shifts: [{ id: 1 }, { id: 2 }] },
            { date: '2026-08-06', shifts: [{ id: 3 }] },
        ],
        2026,
        8,
    );

    const cell = (date: string) => grid.weeks.flat().find((c) => c.date === date)!;

    assert.deepEqual(
        cell('2026-08-05').shifts.map((s) => s.id),
        [1, 2],
    );
    assert.deepEqual(
        cell('2026-08-06').shifts.map((s) => s.id),
        [3],
    );
    assert.deepEqual(cell('2026-08-07').shifts, [], 'a day with no group is an empty cell');
});

test('buildMonthGrid honours a Monday week start', () => {
    // Aug 1 2026 is a Saturday: five leading blanks under a Monday start, not six.
    const grid = buildMonthGrid([], 2026, 8, 1);
    const flat = grid.weeks.flat();

    assert.deepEqual(
        flat.slice(0, 5).map((cell) => cell.date),
        [null, null, null, null, null],
    );
    assert.equal(flat[5].date, '2026-08-01');
});

test('monthsInRange yields the single month a within-month range sits in', () => {
    assert.deepEqual(monthsInRange('2026-08-03', '2026-08-05'), [{ year: 2026, month: 8 }]);
});

test('monthsInRange yields every month a range spans, across a year boundary', () => {
    assert.deepEqual(monthsInRange('2026-11-20', '2027-02-02'), [
        { year: 2026, month: 11 },
        { year: 2026, month: 12 },
        { year: 2027, month: 1 },
        { year: 2027, month: 2 },
    ]);
});
