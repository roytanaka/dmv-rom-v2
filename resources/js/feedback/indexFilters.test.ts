// The Feedback index's remembered filters (#775): the index opens on the type and status
// the viewer chose last, and a URL that carries filters wins.
import assert from 'node:assert/strict';
import test from 'node:test';
import { openingFilters, rememberFilters } from './indexFilters.ts';

const memoryStorage = () => {
    const values = new Map<string, string>();
    const storage = {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => void values.set(key, value),
    };

    return () => storage;
};

const all = { type: null, status: null };

test('with nothing stored, the index opens on Open', () => {
    assert.deepEqual(openingFilters('/feedback', all, memoryStorage()), { type: null, status: 'open' });
});

test('opens on the filters chosen last', () => {
    const storage = memoryStorage();

    rememberFilters({ type: 'bug', status: 'closed' }, storage);

    assert.deepEqual(openingFilters('/feedback', all, storage), { type: 'bug', status: 'closed' });
});

test('filters in the URL win over the stored ones, and become the stored setting', () => {
    const storage = memoryStorage();

    rememberFilters({ type: 'bug', status: 'closed' }, storage);

    assert.equal(openingFilters('/feedback?type=idea', { type: 'idea', status: null }, storage), null);
    assert.deepEqual(openingFilters('/feedback', all, storage), { type: 'idea', status: null });
});

test('a French URL with filters wins too', () => {
    const storage = memoryStorage();

    assert.equal(openingFilters('/fr/retroaction?status=new', { type: null, status: 'new' }, storage), null);
    assert.deepEqual(openingFilters('/fr/retroaction', all, storage), { type: null, status: 'new' });
});

test('after Clear, the index opens on All', () => {
    const storage = memoryStorage();

    rememberFilters(all, storage);

    assert.equal(openingFilters('/feedback', all, storage), null);
});

test('with storage blocked, the index opens on Open with no error', () => {
    const blockedStorage = () => {
        throw new Error('SecurityError');
    };

    assert.doesNotThrow(() => rememberFilters({ type: 'bug', status: null }, blockedStorage));
    assert.deepEqual(openingFilters('/feedback', all, blockedStorage), { type: null, status: 'open' });
    assert.equal(openingFilters('/feedback?type=bug', { type: 'bug', status: null }, blockedStorage), null);
});

test('a stored value that is not valid JSON falls back to Open', () => {
    const storage = memoryStorage();

    storage().setItem('dmv.feedback.indexFilters', '{not json');

    assert.deepEqual(openingFilters('/feedback', all, storage), { type: null, status: 'open' });
});
