<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { t } from '@/chrome/messages';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { PhSignOut, PhTranslate, PhUserCircle } from '@phosphor-icons/vue';

interface Props {
    user: User;
}

defineProps<Props>();
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
                {{ t('nav.user.profile') }}
            </Link>
        </DropdownMenuItem>
        <!-- Language preference — disabled until bilingual routing (ADR-0008/0013). -->
        <DropdownMenuItem class="py-2.5" disabled>
            <PhTranslate class="mr-2 h-4 w-4" />
            {{ t('nav.user.language') }}
            <span class="text-muted-foreground ml-auto text-xs">EN / FR</span>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem class="py-2.5" :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <PhSignOut class="mr-2 h-4 w-4" />
            {{ t('nav.user.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
