/**
 * Unit tests for the minute grid the time pickers offer (#639, ADR-0028).
 *
 * Runs on Node's built-in test runner (`pnpm test:unit`). Prior art: `selfServeShift.test.ts`.
 *
 * The rule under test: a picker offers only the grid steps (12 at the default five minutes, 4 at
 * the self-serve fifteen), and a time already off the grid keeps its own minute on the list, so
 * opening an old record never changes its time behind the editor's back.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { joinTime, minuteOptions, splitTime } from './timeGrid.ts';

test('the default grid offers the twelve five-minute steps', () => {
    assert.deepEqual(minuteOptions(5), [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]);
});

test('the self-serve grid offers the four quarter hours', () => {
    assert.deepEqual(minuteOptions(15), [0, 15, 30, 45]);
});

test('an off-grid minute already set stays on the list, in order', () => {
    assert.deepEqual(minuteOptions(15, 7), [0, 7, 15, 30, 45]);
});

test('an on-grid minute is not listed twice', () => {
    assert.deepEqual(minuteOptions(15, 30), [0, 15, 30, 45]);
});

test('a time splits into hour and minute, and an empty one into nulls', () => {
    assert.deepEqual(splitTime('09:05'), { hour: 9, minute: 5 });
    assert.deepEqual(splitTime(''), { hour: null, minute: null });
});

test('an hour and minute join into the zero-padded HH:mm the forms send', () => {
    assert.equal(joinTime(9, 5), '09:05');
    assert.equal(joinTime(14, 0), '14:00');
});
