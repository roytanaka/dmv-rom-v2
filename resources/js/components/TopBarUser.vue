<script setup lang="ts">
// The avatar menu in the top bar's right slot (#69). The user menu moved here from
// the rail footer; it reuses UserMenuContent so the existing My Profile / Log out
// wiring (route('profile.edit') / route('logout')) is preserved unchanged. The
// trigger is an avatar-only button suited to the dark bar.
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const { getInitials } = useInitials();
const showAvatar = computed(() => user.avatar && user.avatar !== '');
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                aria-label="Account menu"
                class="ring-offset-rom-ink flex shrink-0 items-center rounded-full focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Avatar class="size-8 rounded-full">
                    <AvatarImage v-if="showAvatar" :src="user.avatar ?? ''" :alt="user.name" />
                    <AvatarFallback class="rounded-full bg-white/15 text-sm text-white">
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56 rounded-lg" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
