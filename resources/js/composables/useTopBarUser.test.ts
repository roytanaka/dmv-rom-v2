/**
 * The top-bar avatar reads the signed-in user reactively (#552). When the support
 * operator uses the Role-switcher to Become a Persona, the Inertia visit swaps
 * `auth.user` in place — the avatar initials, name, and photo must follow on that
 * visit, with no full page load.
 *
 * These cover the pure derivation. Vue's reactivity runs under Node's test runner
 * without a DOM, so we drive it with a `reactive` page stand-in and swap the user the
 * way an Inertia visit does, then read the derived values.
 */
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
