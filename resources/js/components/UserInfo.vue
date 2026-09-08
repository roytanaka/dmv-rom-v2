<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

const fullName = computed(() => `${props.user.first_name} ${props.user.last_name}`);

// Compute whether we should show the avatar image
const showAvatar = computed(() => props.user.photo_url && props.user.photo_url !== '');
</script>

<template>
    <Avatar class="h-8 w-8">
        <AvatarImage v-if="showAvatar" :src="user.photo_url ?? ''" :alt="fullName" />
        <AvatarFallback class="text-black">
            {{ getInitials(fullName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ fullName }}</span>
        <span v-if="showEmail" class="text-muted-foreground truncate text-xs">{{ user.email }}</span>
    </div>
</template>
