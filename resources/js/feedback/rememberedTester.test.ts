// The Tester's remembered name (ADR-0029 §5): kept between sends and comments, and
// never a reason for a send to fail.
import assert from 'node:assert/strict';
import test from 'node:test';
import { rememberedName, rememberName } from './testerName.ts';

const memoryStorage = () => {
    const values = new Map<string, string>();
    const storage = {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => void values.set(key, value),
    };

    return () => storage;
};

const blockedStorage = () => {
    throw new Error('SecurityError');
};

test('remembers a name for the next form', () => {
    const storage = memoryStorage();

    rememberName('Pat Tester', storage);

    assert.equal(rememberedName(storage), 'Pat Tester');
});

test('starts empty when no name was kept', () => {
    assert.equal(rememberedName(memoryStorage()), '');
});

test('reads and writes nothing when storage is blocked', () => {
    assert.doesNotThrow(() => rememberName('Pat Tester', blockedStorage));
    assert.equal(rememberedName(blockedStorage), '');
});
