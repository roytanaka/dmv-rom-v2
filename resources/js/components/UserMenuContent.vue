<script setup lang="ts">
import LocaleOptionList from '@/components/LocaleOptionList.vue';
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { SharedData, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhSignOut, PhUserCircle } from '@phosphor-icons/vue';
import { computed } from 'vue';

interface Props {
    user: User;
}

defineProps<Props>();

const page = usePage<SharedData>();

// The language switcher's locale list (ADR-0013). On desktop this lives in the
// top-bar globe (LanguageSwitcher); below lg the globe is hidden and the same list
// renders here (the lg:hidden group below). It is a SELECTABLE list with a checkmark
// on the active locale — never a toggle — so the current selection is always visible.
const localeSwitcher = computed(() => page.props.localeSwitcher);
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
    </DropdownMenuGroup>

    <!-- Language — below lg only (desktop uses the top-bar globe). A selectable list
         with a checkmark on the active locale; a plain anchor forces a full page load
         so the i18n bridge re-boots (#110). Locales with no twin render disabled. -->
    <template v-if="localeSwitcher.options.length">
        <DropdownMenuSeparator class="lg:hidden" />
        <DropdownMenuGroup class="lg:hidden">
            <DropdownMenuLabel class="text-muted-foreground px-2 py-1.5 text-xs font-normal">
                {{ trans('user.language') }}
            </DropdownMenuLabel>
            <LocaleOptionList :switcher="localeSwitcher" icon-class="mr-2 h-4 w-4" />
        </DropdownMenuGroup>
    </template>

    <DropdownMenuSeparator />
    <DropdownMenuItem class="py-2.5" :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <PhSignOut class="mr-2 h-4 w-4" />
            {{ trans('user.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
