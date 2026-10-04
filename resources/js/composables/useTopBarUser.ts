import type { SharedData, User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

export interface TopBarUser {
    user: ComputedRef<User>;
    fullName: ComputedRef<string>;
    showAvatar: ComputedRef<boolean>;
}

// Pure so the reactivity is unit-testable without Inertia; computed tracks auth.user, so a Become visit re-renders the avatar in place (#552).
export function deriveTopBarUser(getUser: () => User): TopBarUser {
    const user = computed(getUser);
    const fullName = computed(() => `${user.value.first_name} ${user.value.last_name}`);
    const showAvatar = computed(() => !!user.value.photo_url);

    return { user, fullName, showAvatar };
}

export function useTopBarUser(): TopBarUser {
    const page = usePage<SharedData>();

    return deriveTopBarUser(() => page.props.auth.user);
}
