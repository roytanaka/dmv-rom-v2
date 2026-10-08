/**
 * Unit tests for the upload's starting Document category (#728, spec #721, ADR-0030 §4).
 * Node's built-in runner (`pnpm test:unit`); prior art: `resources/js/directory/groupColumns.test.ts`.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { defaultUploadCategory } from './uploadCategory.ts';

const categories = [
    { id: 3, name: 'Data Sheets' },
    { id: 7, name: 'Publications' },
];

test('starts at the active Category filter when it is one of the open Folder’s', () => {
    assert.equal(defaultUploadCategory(categories, 7), 7);
});

test('starts at No category with no active filter', () => {
    assert.equal(defaultUploadCategory(categories, null), null);
});

test('starts at No category when the filter names a Document category not in the open Folder', () => {
    assert.equal(defaultUploadCategory(categories, 99), null);
    assert.equal(defaultUploadCategory([], 3), null);
});
