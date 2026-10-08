/**
 * Unit tests for the section-nav active match (#648).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/chrome/activeSection.test.ts`,
 * or `pnpm test:unit`). A section stays active on the pages beneath it — a Schedule permalink
 * lives under the Scheduling section — so the match is the longest href the path sits under.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { activeDestination, activeSectionHref } from './activeSection.ts';

const tabs = ['/groups/docents', '/groups/docents/roster', '/groups/docents/scheduling', '/groups/docents/hours'];

test('an exact match is active', () => {
    assert.equal(activeSectionHref(tabs, '/groups/docents/scheduling'), '/groups/docents/scheduling');
    assert.equal(activeSectionHref(tabs, '/groups/docents'), '/groups/docents');
});

test('a page beneath a section keeps that section active, not the first tab', () => {
    assert.equal(activeSectionHref(tabs, '/groups/docents/scheduling/2'), '/groups/docents/scheduling');
});

test('the query string and fragment are ignored', () => {
    assert.equal(activeSectionHref(tabs, '/groups/docents/scheduling?page=2'), '/groups/docents/scheduling');
    assert.equal(activeSectionHref(tabs, '/groups/docents/hours#entry'), '/groups/docents/hours');
});

test('a shared prefix that is not a path segment does not match', () => {
    assert.equal(activeSectionHref(['/groups/docents'], '/groups/docents-junior'), null);
});

test('French paths match their localised hrefs the same way', () => {
    const fr = ['/fr/groupes/docents', '/fr/groupes/docents/horaires'];
    assert.equal(activeSectionHref(fr, '/fr/groupes/docents/horaires/2'), '/fr/groupes/docents/horaires');
});

test('no match returns null', () => {
    assert.equal(activeSectionHref(tabs, '/news'), null);
    assert.equal(activeSectionHref(['/'], '/news'), null);
});

// The top bar's four links (#741): the active one names the collapsed menu trigger.
const destinations = [
    { key: 'hours', href: '/hours' },
    { key: 'directory', href: '/directory' },
];

test('the active destination is the one the page sits under', () => {
    assert.equal(activeDestination(destinations, '/directory')?.key, 'directory');
    assert.equal(activeDestination(destinations, '/directory?page=2')?.key, 'directory');
    assert.equal(activeDestination(destinations, '/hours/2026')?.key, 'hours');
});

test('no active destination off the four pages', () => {
    assert.equal(activeDestination(destinations, '/dashboard'), null);
});
