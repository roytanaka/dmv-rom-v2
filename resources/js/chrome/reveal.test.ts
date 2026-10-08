// Hide-on-scroll for the phone chrome (#740): the top bar hides on scroll down and shows
// again after a deliberate scroll up. Pure state, no DOM.
import assert from 'node:assert/strict';
import test from 'node:test';
import { INITIAL_REVEAL, nextReveal, type RevealState } from './reveal.ts';

const opts = { hideAfter: 64, pinned: false };

const scroll = (ys: number[], pinnedAt: number[] = []): RevealState =>
    ys.reduce((state, y, i) => nextReveal(state, y, { ...opts, pinned: pinnedAt.includes(i) }), INITIAL_REVEAL);

test('starts shown', () => {
    assert.equal(INITIAL_REVEAL.hidden, false);
});

test('scrolling down past the bar height hides it', () => {
    assert.equal(scroll([100]).hidden, true);
});

test('scrolling down within the bar height keeps it shown', () => {
    assert.equal(scroll([20, 60]).hidden, false);
});

test('a small upward drift does not bring it back', () => {
    assert.equal(scroll([300, 500, 470]).hidden, true);
});

test('scrolling up 40px from the lowest point shows it', () => {
    assert.equal(scroll([300, 500, 460]).hidden, false);
});

test('small upward steps add up to the reveal distance', () => {
    assert.equal(scroll([500, 480, 470, 460]).hidden, false);
});

test('it hides again on the next scroll down', () => {
    assert.equal(scroll([300, 500, 400, 410]).hidden, true);
});

test('it shows at the top of the page', () => {
    assert.equal(scroll([300, 500, 490, 0]).hidden, false);
});

test('a pinned bar does not hide', () => {
    assert.equal(scroll([300, 500], [1]).hidden, false);
});

test('after a pinned stretch, scrolling down hides it again', () => {
    assert.equal(scroll([300, 500, 600], [1]).hidden, true);
});
