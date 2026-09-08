/**
 * Unit tests for the composer's selection core (#489, ADR-0024 §6).
 *
 * Runs on Node's built-in test runner with native TypeScript support — no test
 * framework dependency (`node --test resources/js/emailing/composer.test.ts`, or
 * `pnpm test:unit`). Prior art: `resources/js/scheduling/agenda.test.ts`.
 *
 * The composer names an Audience and lets the sender un-tick or add rows; every
 * derived value the sheet shows — the stored label, the edited flag, the per-Member
 * edits it posts, whether Send may fire, and the roster the Add-people panel filters —
 * is a pure function of the picked Audience and the ticked set. Kept here, out of the
 * Vue layer, so it survives a re-skin and never posts a recipient list (§5): the browser
 * posts only the Audience key and the edits.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import {
    audienceEdits,
    audienceLabelDescriptor,
    bodyHasContent,
    canSend,
    filterRoster,
    isEdited,
    menuFromIndex,
    recipientName,
    rosterIds,
    type AudienceOption,
    type Recipient,
} from './composer.ts';

const audience: AudienceOption = { key: 'whole_group', parameter: null, label: 'Whole group', count: 3 };

const roster: Recipient[] = [
    { id: 1, first_name: 'Ada', last_name: 'Lovelace', photo: null, standing: 'full' },
    { id: 2, first_name: 'Alan', last_name: 'Turing', photo: null, standing: 'full' },
    { id: 3, first_name: 'Grace', last_name: 'Hopper', photo: null, standing: 'trainee' },
];

test('recipientName joins first and last', () => {
    assert.equal(recipientName(roster[0]), 'Ada Lovelace');
});

test('audienceEdits reports removed and added against the base membership', () => {
    const base = [1, 2, 3];
    const ticked = new Set([1, 3, 9]); // 2 removed, 9 added
    assert.deepEqual(audienceEdits(base, ticked), { removed: [2], added: [9] });
});

test('audienceEdits is empty when the ticked set matches the base exactly', () => {
    assert.deepEqual(audienceEdits([1, 2, 3], new Set([1, 2, 3])), { removed: [], added: [] });
});

test('isEdited is false for a hand-picked selection (no base Audience)', () => {
    assert.equal(isEdited(null, [], new Set([1, 2])), false);
});

test('isEdited is true once the ticked set diverges from the base', () => {
    assert.equal(isEdited(audience, [1, 2, 3], new Set([1, 2, 3])), false);
    assert.equal(isEdited(audience, [1, 2, 3], new Set([1, 2])), true);
});

test('audienceLabelDescriptor names a hand-picked selection by its count', () => {
    assert.deepEqual(audienceLabelDescriptor(null, [], new Set([1, 2])), { kind: 'hand_picked', count: 2 });
});

test('audienceLabelDescriptor keeps the Audience name when unedited', () => {
    assert.deepEqual(audienceLabelDescriptor(audience, [1, 2, 3], new Set([1, 2, 3])), { kind: 'named', label: 'Whole group' });
});

test('audienceLabelDescriptor appends the removed count once anyone is dropped', () => {
    assert.deepEqual(audienceLabelDescriptor(audience, [1, 2, 3], new Set([1])), { kind: 'edited', label: 'Whole group', removed: 2 });
});

test('bodyHasContent ignores empty rich-text scaffolding', () => {
    assert.equal(bodyHasContent(''), false);
    assert.equal(bodyHasContent('<p></p>'), false);
    assert.equal(bodyHasContent('<p><br></p>'), false);
    assert.equal(bodyHasContent('   '), false);
    assert.equal(bodyHasContent('<p>Hello</p>'), true);
});

test('canSend requires a subject, a non-empty body, and at least one recipient', () => {
    assert.equal(canSend({ subject: 'Hi', body: '<p>Body</p>', recipientCount: 1 }), true);
    assert.equal(canSend({ subject: '  ', body: '<p>Body</p>', recipientCount: 1 }), false);
    assert.equal(canSend({ subject: 'Hi', body: '<p></p>', recipientCount: 1 }), false);
    assert.equal(canSend({ subject: 'Hi', body: '<p>Body</p>', recipientCount: 0 }), false);
});

test('filterRoster matches on the full name, case-insensitively', () => {
    assert.deepEqual(
        filterRoster(roster, 'la').map((r) => r.id),
        [1, 2], // "Ada Lovelace" (lovelace) and "Alan Turing" (alan)
    );
    assert.deepEqual(
        filterRoster(roster, 'HOPPER').map((r) => r.id),
        [3],
    );
    assert.equal(filterRoster(roster, '  ').length, 3);
});

test('rosterIds is the tick-all target: every row on the page', () => {
    assert.deepEqual([...rosterIds(roster)], [1, 2, 3]);
});

test('menuFromIndex drops the hand-pick key and reports it may be hand-picked', () => {
    const index: AudienceOption[] = [
        { key: 'whole_group', parameter: null, label: 'Whole group', count: 3 },
        { key: 'hand_picked', parameter: null, label: 'Hand-picked', count: 0 },
    ];
    const { audiences, canHandPick } = menuFromIndex(index);
    assert.deepEqual(
        audiences.map((a) => a.key),
        ['whole_group'],
    );
    assert.equal(canHandPick, true);
});

test('menuFromIndex reports no hand-pick when the server offered none (a plain Directory member)', () => {
    const index: AudienceOption[] = [
        { key: 'board_of_directors', parameter: null, label: 'Board of Directors', count: 9 },
        { key: 'committee_chairs', parameter: null, label: 'Committee Chairs', count: 12 },
        { key: 'all_chairs', parameter: null, label: 'All Chairs', count: 15 },
    ];
    const { audiences, canHandPick } = menuFromIndex(index);
    assert.equal(audiences.length, 3);
    assert.equal(canHandPick, false);
});
