<script setup lang="ts">
// The avatar menu in the top bar's right slot (#69). The user menu moved here from
// the rail footer; it reuses UserMenuContent so the existing My Profile / Log out
// wiring (localized /settings/profile href / route('logout')) is preserved unchanged. The
// trigger is an avatar-only button suited to the dark bar.
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { useTopBarUser } from '@/composables/useTopBarUser';

// Reactive: a Become / Return visit that swaps auth.user re-renders the avatar in place (#552).
const { user, fullName, showAvatar } = useTopBarUser();

const { getInitials } = useInitials();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                aria-label="Account menu"
                class="ring-offset-rom-ink flex shrink-0 items-center rounded-full focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <!-- bg-transparent drops the Avatar's default light bg-secondary so the
                     translucent fallback sits on the black bar — white initials on a
                     light disc were unreadable otherwise. -->
                <Avatar class="size-10 bg-transparent">
                    <AvatarImage v-if="showAvatar" :src="user.photo_url ?? ''" :alt="fullName" />
                    <AvatarFallback class="bg-white/20 text-sm text-white">
                        {{ getInitials(fullName) }}
                    </AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <!-- Square corners — ROM identity. The component defaults to rounded-none;
             no rounded-* override here (the avatar inside stays the only circle). -->
        <DropdownMenuContent class="w-56" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
