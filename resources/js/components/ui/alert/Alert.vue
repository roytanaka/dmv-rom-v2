<script setup lang="ts">
// ROM-tuned shadcn-vue Alert: see index.ts. `role` defaults to upstream's `alert`; a calm
// state banner (an archived Group) passes `status` instead.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import type { AlertVariants } from '.';
import { alertVariants } from '.';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        variant?: AlertVariants['variant'];
        role?: 'alert' | 'status';
    }>(),
    { role: 'alert' },
);
</script>

<template>
    <div :class="cn(alertVariants({ variant }), props.class)" :role="role">
        <slot />
    </div>
</template>
