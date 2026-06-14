<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { SharedData, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhSignOut, PhTranslate, PhUserCircle } from '@phosphor-icons/vue';
import { computed } from 'vue';

interface Props {
    user: User;
}

defineProps<Props>();

const page = usePage<SharedData>();

// The current page's twin in the other locale, or null when no twin is
// registered — in which case the switcher is hidden so the Volunteer is never
// offered a link that 404s (#110). A plain anchor (not an Inertia <Link>) forces
// a full page load so the i18n bridge re-boots in the target locale.
const localeSwitch = computed(() => page.props.localeSwitch);
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem class="py-2.5" :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <PhUserCircle class="mr-2 h-4 w-4" />
                {{ trans('user.profile') }}
            </Link>
        </DropdownMenuItem>
        <!-- Language switcher — navigates to the current page's twin in the other
             locale (ADR-0008). Hidden when the page has no registered twin. -->
        <DropdownMenuItem v-if="localeSwitch" class="py-2.5" :as-child="true">
            <a class="flex w-full items-center" :href="localeSwitch.url">
                <PhTranslate class="mr-2 h-4 w-4" />
                {{ trans('user.language') }}
                <span class="text-muted-foreground ml-auto text-xs">{{ localeSwitch.locale.toUpperCase() }}</span>
            </a>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem class="py-2.5" :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <PhSignOut class="mr-2 h-4 w-4" />
            {{ trans('user.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
