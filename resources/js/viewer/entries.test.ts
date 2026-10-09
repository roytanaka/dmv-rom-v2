// The image viewer (#776): only the image types every browser displays are shown inline.
import assert from 'node:assert/strict';
import test from 'node:test';
import { isViewableImage, stepIndex } from './entries.ts';

// Previous and next (#781): one entry at a time, stopping at either end of the set.
test('steps to the next and the previous entry', () => {
    assert.equal(stepIndex(1, 1, 5), 2);
    assert.equal(stepIndex(1, -1, 5), 0);
});

test('stops at the first and the last entry', () => {
    assert.equal(stepIndex(0, -1, 5), 0);
    assert.equal(stepIndex(4, 1, 5), 4);
});

test('shows PNG, JPEG, WebP, and GIF images', () => {
    for (const type of ['image/png', 'image/jpeg', 'image/webp', 'image/gif']) {
        assert.equal(isViewableImage(type), true, type);
    }
});

test('never shows an SVG or any other type as an image', () => {
    for (const type of ['image/svg+xml', 'text/html', 'application/pdf', 'image/heic', '']) {
        assert.equal(isViewableImage(type), false, type);
    }
});
