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
import { groupShiftsByDay, orgDayKey } from './agenda.ts';

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
