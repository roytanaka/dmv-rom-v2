/**
 * Unit tests for the record form's boxes (#646, ADR-0023).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/scheduling/recordDraft.test.ts`,
 * or `pnpm test:unit`). Prior art: `resources/js/scheduling/agenda.test.ts`.
 *
 * The defect under test: a `type="number"` box hands its v-model back as a number once the user
 * types, but a pre-filled or empty box holds a string. Every case below types into a box (a
 * number) and then submits. The ADR-0023 rule rides along: an empty box is not recorded, a
 * typed zero is a real zero.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRecordPayload, canSubmitRecord, NO_RECORD_ERRORS, recordErrorsFrom, type RecordDraft } from './recordDraft.ts';

const PLAIN = { collectsExtraInteractions: false, collectsVisitorProvenance: false, writesComment: false };
const TOUR = { collectsExtraInteractions: true, collectsVisitorProvenance: false, writesComment: false };
const GDR = { collectsExtraInteractions: false, collectsVisitorProvenance: true, writesComment: false };
// The viewer's own seat, where the comment box shows (#655).
const OWN = { ...PLAIN, writesComment: true };

const emptyProvenance = {
    visitors_france_europe: '',
    visitors_quebec: '',
    visitors_toronto: '',
    visitors_rest_of_canada: '',
    visitors_other_countries: '',
};

const draft = (overrides: Partial<RecordDraft> = {}): RecordDraft => ({
    count: '',
    extra: '',
    provenance: { ...emptyProvenance },
    comment: '',
    ...overrides,
});

test('an empty count box keeps Record shift disabled', () => {
    assert.equal(canSubmitRecord(draft(), PLAIN), false);
});

test('typing a count enables Record shift and sends it', () => {
    const typed = draft({ count: 12 });

    assert.equal(canSubmitRecord(typed, PLAIN), true);
    assert.deepEqual(buildRecordPayload(typed, PLAIN), { count: 12, extra: null, provenance: null });
});

test('a typed zero is a real zero, not "not recorded"', () => {
    const typed = draft({ count: 0 });

    assert.equal(canSubmitRecord(typed, PLAIN), true);
    assert.deepEqual(buildRecordPayload(typed, PLAIN), { count: 0, extra: null, provenance: null });
});

test('a pre-filled string count still submits', () => {
    assert.deepEqual(buildRecordPayload(draft({ count: '7' }), PLAIN), { count: 7, extra: null, provenance: null });
});

test('typing only the extra box sends the new extra with the pre-filled count', () => {
    const typed = draft({ count: '20', extra: 5 });

    assert.equal(canSubmitRecord(typed, TOUR), true);
    assert.deepEqual(buildRecordPayload(typed, TOUR), { count: 20, extra: 5, provenance: null });
});

test('a typed zero extra saves as zero; a blank extra files null', () => {
    assert.equal(buildRecordPayload(draft({ count: 3, extra: 0 }), TOUR).extra, 0);
    assert.equal(buildRecordPayload(draft({ count: 3, extra: '' }), TOUR).extra, null);
});

test('the extra is dropped where the Group does not collect it', () => {
    assert.equal(buildRecordPayload(draft({ count: 3, extra: 4 }), PLAIN).extra, null);
});

test('typed origins that sum to the count submit on GDR', () => {
    const typed = draft({
        count: 10,
        provenance: {
            visitors_france_europe: 1,
            visitors_quebec: 2,
            visitors_toronto: 3,
            visitors_rest_of_canada: 4,
            visitors_other_countries: 0,
        },
    });

    assert.equal(canSubmitRecord(typed, GDR), true);
    assert.deepEqual(buildRecordPayload(typed, GDR).provenance, {
        visitors_france_europe: 1,
        visitors_quebec: 2,
        visitors_toronto: 3,
        visitors_rest_of_canada: 4,
        visitors_other_countries: 0,
    });
});

test('a blank origin box blocks Record shift on GDR', () => {
    const typed = draft({ count: 6, provenance: { ...emptyProvenance, visitors_quebec: 6 } });

    assert.equal(canSubmitRecord(typed, GDR), false);
});

// The sum is the server's rule (#649): a disabled button gives no reason, so a filled set that
// does not add up goes out, and the server's message names both totals under the origin boxes.
test('origins that do not sum to the count still submit on GDR, for the server to refuse', () => {
    const typed = draft({
        count: 10,
        provenance: {
            visitors_france_europe: 1,
            visitors_quebec: 1,
            visitors_toronto: 1,
            visitors_rest_of_canada: 1,
            visitors_other_countries: 1,
        },
    });

    assert.equal(canSubmitRecord(typed, GDR), true);
});

// --- A refused save (#649) — the server's message lands under the box it applies to ---

test('a refused decimal count shows the whole-number message under Visitors served', () => {
    const errors = recordErrorsFrom({ visitor_count: 'Enter a whole number of visitors.' });

    assert.deepEqual(errors, { ...NO_RECORD_ERRORS, count: 'Enter a whole number of visitors.' });
});

test('a refused negative extra shows its message under the extra box', () => {
    const errors = recordErrorsFrom({ extra_interaction_count: 'The number of extra interactions cannot be negative.' });

    assert.deepEqual(errors, { ...NO_RECORD_ERRORS, extra: 'The number of extra interactions cannot be negative.' });
});

test('a refused origin sum shows the sum message under the origin boxes', () => {
    const errors = recordErrorsFrom({ visitors_france_europe: 'The five origins add up to 9, but the visitor count is 10.' });

    assert.deepEqual(errors, { ...NO_RECORD_ERRORS, provenance: 'The five origins add up to 9, but the visitor count is 10.' });
});

test('an error on any origin box shows under the origin boxes', () => {
    const errors = recordErrorsFrom({ visitors_toronto: 'The number of visitors cannot be negative.' });

    assert.deepEqual(errors, { ...NO_RECORD_ERRORS, provenance: 'The number of visitors cannot be negative.' });
});

test('a save the server accepts clears every error', () => {
    assert.deepEqual(recordErrorsFrom({}), NO_RECORD_ERRORS);
});

// --- The comment on the viewer's own entry (#655) ---

test('the comment rides with the count on the viewer’s own seat', () => {
    assert.deepEqual(buildRecordPayload(draft({ count: 12, comment: 'A visitor asked about the whale.' }), OWN), {
        count: 12,
        extra: null,
        provenance: null,
        comment: 'A visitor asked about the whale.',
    });
});

test('a blank comment files null, so a volunteer can clear it', () => {
    assert.equal(buildRecordPayload(draft({ count: 12, comment: '   ' }), OWN).comment, null);
});

test('an officer’s correction never sends a comment', () => {
    assert.equal('comment' in buildRecordPayload(draft({ count: 12, comment: 'Rewritten.' }), PLAIN), false);
});

test('a comment with no count keeps Record shift disabled', () => {
    assert.equal(canSubmitRecord(draft({ comment: 'Busy morning.' }), OWN), false);
});

test('a refused long comment shows its message under the comment box', () => {
    const errors = recordErrorsFrom({ comment: 'The comment can be at most 2,000 characters.' });

    assert.deepEqual(errors, { ...NO_RECORD_ERRORS, comment: 'The comment can be at most 2,000 characters.' });
});

test('an error on a field the form does not show is ignored', () => {
    assert.deepEqual(recordErrorsFrom({ member_id: 'Nope.' }), NO_RECORD_ERRORS);
});
