/**
 * Unit tests for the self-serve shift end (#585, PRD #576, ADR-0026 §2).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/scheduling/selfServeShift.test.ts`,
 * or `pnpm test:unit`). Prior art: `resources/js/scheduling/signOut.test.ts`.
 *
 * The rule under test: the end is the start plus units × unit-minutes. The load-bearing cases
 * are the GI unit (45 minutes) at one unit and at the eight-unit ceiling, and a non-GI unit
 * length, so the function is proven to read its unit setting rather than assume 45.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { deriveEndsAt } from './selfServeShift.ts';

const STARTS_AT = new Date('2026-09-10T14:00:00Z');

test('one GI unit runs 45 minutes', () => {
    assert.equal(deriveEndsAt(STARTS_AT, 1, 45).toISOString(), '2026-09-10T14:45:00.000Z');
});

test('the eight-unit ceiling runs six hours', () => {
    assert.equal(deriveEndsAt(STARTS_AT, 8, 45).toISOString(), '2026-09-10T20:00:00.000Z');
});

test('the unit length is read, not assumed — 60-minute units', () => {
    assert.equal(deriveEndsAt(STARTS_AT, 3, 60).toISOString(), '2026-09-10T17:00:00.000Z');
});
