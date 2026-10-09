// A Document's size in the page's language (#781): the library list and the viewer's file card.
import assert from 'node:assert/strict';
import test from 'node:test';
import { formatFileSize } from './fileSize.ts';

test('shows kilobytes under a megabyte, megabytes under a gigabyte, then gigabytes', () => {
    assert.equal(formatFileSize(2048, 'en'), '2 kB');
    assert.equal(formatFileSize(15 * 1024 ** 2, 'en'), '15 MB');
    assert.equal(formatFileSize(3 * 1024 ** 3, 'en'), '3 GB');
});

test('writes the size in French on a French page', () => {
    assert.match(formatFileSize(1536, 'fr'), /^1,5\s+ko$/u);
});

test('shows nothing for a Document with no size', () => {
    assert.equal(formatFileSize(null, 'en'), '');
});
