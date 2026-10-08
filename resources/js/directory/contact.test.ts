/**
 * Unit tests for the roster's tappable phone link (#750). Node's built-in runner
 * (`pnpm test:unit`); prior art: `resources/js/directory/groupColumns.test.ts`.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { telHref } from './contact.ts';

test('telHref keeps only the digits of a formatted number', () => {
    assert.equal(telHref('(416) 586-8000'), 'tel:4165868000');
    assert.equal(telHref('416.586.8000'), 'tel:4165868000');
});

test('telHref keeps a leading plus for an international number', () => {
    assert.equal(telHref('+1 416-586-8000'), 'tel:+14165868000');
});

test('telHref dials the main number and drops an extension', () => {
    assert.equal(telHref('416-586-8000 ext. 123'), 'tel:4165868000');
    assert.equal(telHref('416-586-8000 x123'), 'tel:4165868000');
    assert.equal(telHref('416-586-8000 poste 123'), 'tel:4165868000');
});

test('telHref gives no link for text with no digits', () => {
    assert.equal(telHref('n/a'), undefined);
});
