/**
 * Unit tests for the Tours page search (Feedback items 85, 84).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/tours/toursSearch.test.ts`, or
 * `pnpm test:unit`). Prior art: `resources/js/emailing/composer.test.ts`.
 *
 * One box filters the Tour list on Tour name or Member name, case-insensitive. A Tour kept
 * because a Member matched opens by itself, and the matched part of a name is marked.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { searchTours, splitMatch, type SearchableTour } from './toursSearch.ts';

const tour = (id: number, name: string, members: string[]): SearchableTour => ({
    id,
    name,
    members: members.map((member, index) => ({ memberId: id * 100 + index, name: member })),
});

const tours = [tour(1, 'Ancient Egypt', ['Margaret Chen', 'Ravi Singh']), tour(2, 'Dinosaurs', ['Hassan Murphy']), tour(3, 'Museum Highlights', [])];

test('an empty query keeps every Tour and opens none', () => {
    const result = searchTours(tours, '   ');

    assert.deepEqual(
        result.map((row) => row.tour.id),
        [1, 2, 3],
    );
    assert.ok(result.every((row) => !row.memberMatch));
});

test('a Tour name match keeps the Tour, closed', () => {
    const result = searchTours(tours, 'EGY');

    assert.deepEqual(
        result.map((row) => [row.tour.id, row.memberMatch]),
        [[1, false]],
    );
});

test('a Member name match keeps the Tour and opens it', () => {
    const result = searchTours(tours, 'murphy');

    assert.deepEqual(
        result.map((row) => [row.tour.id, row.memberMatch]),
        [[2, true]],
    );
});

test('a query that matches a Member in several Tours keeps each of them', () => {
    const result = searchTours([...tours, tour(4, 'Family', ['Ravi Silva'])], 'ravi');

    assert.deepEqual(
        result.map((row) => row.tour.id),
        [1, 4],
    );
});

test('a query that matches nothing keeps no Tour', () => {
    assert.deepEqual(searchTours(tours, 'zebra'), []);
});

test('splitMatch marks each case-insensitive hit and keeps the original case', () => {
    assert.deepEqual(splitMatch('Ravi Singh', 'SING'), [
        { text: 'Ravi ', match: false },
        { text: 'Sing', match: true },
        { text: 'h', match: false },
    ]);
    assert.deepEqual(splitMatch('Anna Hanna', 'anna'), [
        { text: 'Anna', match: true },
        { text: ' H', match: false },
        { text: 'anna', match: true },
    ]);
});

test('splitMatch returns the whole name unmarked for an empty query', () => {
    assert.deepEqual(splitMatch('Ravi Singh', ' '), [{ text: 'Ravi Singh', match: false }]);
});
