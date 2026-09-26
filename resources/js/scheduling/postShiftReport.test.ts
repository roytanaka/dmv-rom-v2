/**
 * Unit tests for the Post-shift report section's entry states (#652, PRD #651).
 *
 * Runs on Node's built-in test runner (`pnpm test:unit`). Prior art: `recordDraft.test.ts`.
 *
 * The rules under test: which seat, if any, shows the form (one per card); whether every other
 * seat reads as a summary or "No count yet"; and the "N of M recorded" tally.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { entryState, openFormSeat, recordedTally } from './postShiftReport.ts';

const me = { id: 1, visitor_count: null };
const peer = { id: 2, visitor_count: 9 };
const quiet = { id: 3, visitor_count: null };

test('the viewer’s unrecorded own seat opens as the form inside the window', () => {
    assert.equal(openFormSeat({ seats: [me, peer], ownSeatId: 1, canRecordOwn: true, editingSeatId: null }), 1);
});

test('no form opens before the sign-out window', () => {
    assert.equal(openFormSeat({ seats: [me, peer], ownSeatId: 1, canRecordOwn: false, editingSeatId: null }), null);
});

test('a recorded own seat stays a summary until Change', () => {
    const recorded = { id: 1, visitor_count: 12 };

    assert.equal(openFormSeat({ seats: [recorded, peer], ownSeatId: 1, canRecordOwn: true, editingSeatId: null }), null);
    assert.equal(openFormSeat({ seats: [recorded, peer], ownSeatId: 1, canRecordOwn: true, editingSeatId: 1 }), 1);
});

test('a reader with no seat gets no form', () => {
    assert.equal(openFormSeat({ seats: [peer, quiet], ownSeatId: null, canRecordOwn: false, editingSeatId: null }), null);
});

test('an officer’s chosen seat takes the one form from the own seat', () => {
    assert.equal(openFormSeat({ seats: [me, peer], ownSeatId: 1, canRecordOwn: true, editingSeatId: 2 }), 2);
});

test('an entry reads as the form, a summary, or "No count yet"', () => {
    assert.equal(entryState(me, 1), 'form');
    assert.equal(entryState(peer, 1), 'summary');
    assert.equal(entryState(quiet, 1), 'no-count');
    assert.equal(entryState(me, null), 'no-count');
});

test('a recorded zero is a summary, not "No count yet"', () => {
    assert.equal(entryState({ id: 4, visitor_count: 0 }, null), 'summary');
});

test('the tally counts seats with a count, zero included', () => {
    assert.deepEqual(recordedTally([me, peer, { id: 4, visitor_count: 0 }]), { recorded: 2, total: 3 });
    assert.deepEqual(recordedTally([]), { recorded: 0, total: 0 });
});
