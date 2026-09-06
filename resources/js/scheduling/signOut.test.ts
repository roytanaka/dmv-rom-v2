/**
 * Unit tests for the sign-out window (#445, PRD #443, ADR-0023 §5).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/scheduling/signOut.test.ts`,
 * or `pnpm test:unit`). Prior art: `resources/js/scheduling/agenda.test.ts`.
 *
 * The rule under test: the panel opens five minutes before a Shift ends and never closes. The
 * load-bearing cases are the two boundaries — a minute before the window opens (closed) and the
 * moment it opens (open) — and the no-deadline case a month later (still open). Instants only;
 * the org wall clock plays no part.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { withinSignOutWindow } from './signOut.ts';

const ENDS_AT = '2026-09-10T13:00:00Z';

test('the panel is closed before the five-minute window opens', () => {
    // 12:54Z is one minute before the window opens (12:55Z).
    assert.equal(withinSignOutWindow(ENDS_AT, new Date('2026-09-10T12:54:00Z')), false);
});

test('the panel opens exactly five minutes before the Shift ends', () => {
    assert.equal(withinSignOutWindow(ENDS_AT, new Date('2026-09-10T12:55:00Z')), true);
});

test('the panel stays open through the Shift and past its end', () => {
    assert.equal(withinSignOutWindow(ENDS_AT, new Date('2026-09-10T13:00:00Z')), true);
    assert.equal(withinSignOutWindow(ENDS_AT, new Date('2026-09-10T14:00:00Z')), true);
});

test('the panel has no deadline — still open a month later', () => {
    assert.equal(withinSignOutWindow(ENDS_AT, new Date('2026-10-10T09:00:00Z')), true);
});
