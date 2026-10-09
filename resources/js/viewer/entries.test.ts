// The image viewer (#776): only the image types every browser displays are shown inline.
import assert from 'node:assert/strict';
import test from 'node:test';
import { isViewableImage } from './entries.ts';

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
