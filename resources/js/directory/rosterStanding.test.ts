/**
 * Unit tests for the roster's DMV-wide standing marker (#877). Node's built-in runner
 * (`pnpm test:unit`); prior art: `resources/js/directory/groupColumns.test.ts`.
 *
 * A roster manager sees a Member's DMV-wide standing (Category) when it is not Active.
 * Other viewers never see it.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { dmvStandingMarker } from './rosterStanding.ts';

test('a roster manager sees an LOA Member marked', () => {
    assert.equal(dmvStandingMarker('loa', true), 'loa');
});

test('a roster manager sees any other non-Active standing marked', () => {
    assert.equal(dmvStandingMarker('sustaining', true), 'sustaining');
    assert.equal(dmvStandingMarker('honourary', true), 'honourary');
});

test('an Active Member carries no marker', () => {
    assert.equal(dmvStandingMarker('active', true), null);
});

test('a viewer who does not manage the roster sees no marker', () => {
    assert.equal(dmvStandingMarker('loa', false), null);
});
