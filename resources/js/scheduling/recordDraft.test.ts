/**
 * Unit tests for the sign-out form's boxes (#646, ADR-0023).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/scheduling/recordDraft.test.ts`,
 * or `pnpm test:unit`). Prior art: `resources/js/scheduling/signOut.test.ts`.
 *
 * The defect under test: a `type="number"` box hands its v-model back as a number once the user
 * types, but a pre-filled or empty box holds a string. Every case below types into a box (a
 * number) and then submits. The ADR-0023 rule rides along: an empty box is not recorded, a
 * typed zero is a real zero.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRecordPayload, canSubmitRecord, type RecordDraft } from './recordDraft.ts';

const PLAIN = { collectsExtraInteractions: false, collectsVisitorProvenance: false };
const TOUR = { collectsExtraInteractions: true, collectsVisitorProvenance: false };
const GDR = { collectsExtraInteractions: false, collectsVisitorProvenance: true };

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
    ...overrides,
});

test('an empty count box keeps Sign out disabled', () => {
    assert.equal(canSubmitRecord(draft(), PLAIN), false);
});

test('typing a count enables Sign out and sends it', () => {
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

test('a blank origin box blocks Sign out on GDR', () => {
    const typed = draft({ count: 6, provenance: { ...emptyProvenance, visitors_quebec: 6 } });

    assert.equal(canSubmitRecord(typed, GDR), false);
});

test('origins that do not sum to the count block Sign out on GDR', () => {
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

    assert.equal(canSubmitRecord(typed, GDR), false);
});
