<script setup lang="ts">
// Customised (#739): sticks to the bottom of the scrolling dialog body. The offsets cancel the body's
// p-6 and half its gap-4; see DialogContent.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div :class="cn('sticky -bottom-6 z-10 -mx-6 -mt-2 -mb-6 bg-background px-6 pt-2 pb-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', props.class)">
        <slot />
    </div>
</template>
