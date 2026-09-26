/**
 * Unit tests for the Post-shift report section's entry states (#652, PRD #651).
 *
 * Runs on Node's built-in test runner (`pnpm test:unit`). Prior art: `recordDraft.test.ts`.
 *
 * The rules under test: which seat, if any, shows the form (one per card); whether every other
 * seat reads as a summary or "No count yet"; the "N of M recorded" tally; and the parts of the
 * "Last edited by" line (#654); and the one-line comment preview (#655).
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { commentPreview, entryState, lastEditedLine, openFormSeat, recordedTally, showsChange } from './postShiftReport.ts';

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

test('Change shows on a summary or "No count yet" where the server says the viewer may change it (#653)', () => {
    assert.equal(showsChange({ id: 2, can_record: true }, 'summary'), true);
    assert.equal(showsChange({ id: 3, can_record: true }, 'no-count'), true);
});

test('Change hides where the server says no, or sent no verdict', () => {
    assert.equal(showsChange({ id: 2, can_record: false }, 'summary'), false);
    assert.equal(showsChange({ id: 2 }, 'summary'), false);
});

test('Change hides on the entry that already shows the form', () => {
    assert.equal(showsChange({ id: 1, can_record: true }, 'form'), false);
});

test('the tally counts seats with a count, zero included', () => {
    assert.deepEqual(recordedTally([me, peer, { id: 4, visitor_count: 0 }]), { recorded: 2, total: 3 });
    assert.deepEqual(recordedTally([]), { recorded: 0, total: 0 });
});

const stamped = { id: 4, visitor_count: 14, last_edited: { name: 'Ada Lovelace', at: '2026-09-10T18:05:00+00:00' } };

// ICU versions differ on the space before AM/PM (plain or narrow no-break), so compare with
// every space made plain.
const plain = (line: { name: string; time: string } | null) => line && { ...line, time: line.time.replace(/\s/g, ' ') };

test('the last-edited line names the editor and shows the time on the org wall clock', () => {
    assert.deepEqual(plain(lastEditedLine(stamped, 'en', 'America/Toronto')), { name: 'Ada Lovelace', time: 'Sep 10, 2026, 2:05 PM' });
});

test('the last-edited time follows the viewer’s locale', () => {
    assert.deepEqual(plain(lastEditedLine(stamped, 'fr', 'America/Toronto')), { name: 'Ada Lovelace', time: '10 sept. 2026, 14:05' });
});

test('an entry with no stamp has no last-edited line', () => {
    assert.equal(lastEditedLine({ id: 5, visitor_count: 3, last_edited: null }, 'en', 'America/Toronto'), null);
    assert.equal(lastEditedLine({ id: 6, visitor_count: 3 }, 'en', 'America/Toronto'), null);
});

test('a comment preview reads on one line', () => {
    assert.equal(
        commentPreview({ id: 1, comment: '  A visitor asked\nabout the whale.\n\nAll good.  ' }),
        'A visitor asked about the whale. All good.',
    );
});

test('an entry with no comment, or one the viewer does not receive, has no preview', () => {
    assert.equal(commentPreview({ id: 1, comment: null }), null);
    assert.equal(commentPreview({ id: 1, comment: '   ' }), null);
    assert.equal(commentPreview({ id: 2 }), null);
});
