<script setup lang="ts">
import { cn } from '@/lib/utils';
import { AvatarRoot } from 'radix-vue';
import type { HTMLAttributes } from 'vue';
import { avatarVariant, type AvatarVariants } from '.';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        size?: AvatarVariants['size'];
    }>(),
    {
        size: 'sm',
    },
);
</script>

<template>
    <AvatarRoot :class="cn(avatarVariant({ size }), props.class)">
        <slot />
    </AvatarRoot>
</template>
