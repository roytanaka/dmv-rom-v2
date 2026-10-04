// Reactive derivation for the top-bar avatar: computed values follow auth.user swaps without a DOM or Inertia context (#552).
import type { User } from '@/types';
import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from 'vue';
import { deriveTopBarUser } from './useTopBarUser.ts';

const makeUser = (overrides: Partial<User> = {}): User =>
    ({
        id: 1,
        first_name: 'Sam',
        last_name: 'Operator',
        email: 'operator@dmv.test',
        photo_url: null,
        ...overrides,
    }) as User;

test('name derives from the current user', () => {
    const { fullName } = deriveTopBarUser(() => makeUser({ first_name: 'Margaret', last_name: 'Chen' }));

    assert.equal(fullName.value, 'Margaret Chen');
});

test('name follows an auth.user swap without a reload', () => {
    const page = reactive({ auth: { user: makeUser({ first_name: 'Sam', last_name: 'Operator' }) } });
    const { fullName } = deriveTopBarUser(() => page.auth.user);

    assert.equal(fullName.value, 'Sam Operator');

    // The Become visit replaces auth.user in place.
    page.auth.user = makeUser({ first_name: 'Margaret', last_name: 'Chen' });

    assert.equal(fullName.value, 'Margaret Chen');
});

test('showAvatar follows an auth.user swap', () => {
    const page = reactive({ auth: { user: makeUser({ photo_url: null }) } });
    const { showAvatar } = deriveTopBarUser(() => page.auth.user);

    assert.equal(showAvatar.value, false);

    page.auth.user = makeUser({ photo_url: 'https://rom.test/photos/margaret.jpg' });

    assert.equal(showAvatar.value, true);
});
