/**
 * Unit tests for the Directory's Group-dependent columns (#700). Node's built-in runner
 * (`pnpm test:unit`); prior art: `resources/js/scheduling/agenda.test.ts`.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { otherGroups, rolesIn } from './groupColumns.ts';

const groups = [
    { name: 'Docents', slug: 'docents', roles: ['chair', 'scheduler'] },
    { name: 'Executive', slug: 'executive', roles: [] },
];

test('rolesIn gives the roles in the picked Group', () => {
    assert.deepEqual(rolesIn(groups, 'docents'), ['chair', 'scheduler']);
});

test('rolesIn gives no roles for a Group with none, one not held, or no Group picked', () => {
    assert.deepEqual(rolesIn(groups, 'executive'), []);
    assert.deepEqual(rolesIn(groups, 'reception'), []);
    assert.deepEqual(rolesIn(groups, ''), []);
});

test('otherGroups drops the picked Group', () => {
    assert.deepEqual(
        otherGroups(groups, 'docents').map((group) => group.slug),
        ['executive'],
    );
});

test('otherGroups keeps every Group when no Group is picked', () => {
    assert.deepEqual(
        otherGroups(groups, '').map((group) => group.slug),
        ['docents', 'executive'],
    );
});
