/**
 * Unit tests for the section-nav active match (#648).
 *
 * Runs on Node's built-in test runner (`node --test resources/js/chrome/activeSection.test.ts`,
 * or `pnpm test:unit`). A section stays active on the pages beneath it — a Schedule permalink
 * lives under the Scheduling section — so the match is the longest href the path sits under.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { activeSectionHref } from './activeSection.ts';

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
