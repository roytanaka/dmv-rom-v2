// The edge fade on a pinned data grid (#749): only the side with more columns fades, and the
// start fade begins after the pinned name column so the name never fades.
import assert from 'node:assert/strict';
import test from 'node:test';
import { fadeMask } from './fadeMask.ts';

test('a table that fits gets no mask', () => {
    assert.equal(fadeMask({ start: false, end: false, inset: 120 }), undefined);
});

test('at the start, only the end edge fades', () => {
    assert.equal(fadeMask({ start: false, end: true, inset: 120 }), 'linear-gradient(to right, black, black calc(100% - 2rem), transparent)');
});

test('at the end, the fade starts after the pinned column', () => {
    assert.equal(
        fadeMask({ start: true, end: false, inset: 120 }),
        'linear-gradient(to right, black 120px, transparent 120px, black calc(120px + 2rem), black)',
    );
});

test('in the middle, both edges fade', () => {
    assert.equal(
        fadeMask({ start: true, end: true, inset: 96.5 }),
        'linear-gradient(to right, black 96.5px, transparent 96.5px, black calc(96.5px + 2rem), black calc(100% - 2rem), transparent)',
    );
});
