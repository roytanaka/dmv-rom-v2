<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('sticky -top-6 z-10 -mx-6 -mt-6 -mb-2 bg-background px-6 pt-6 pb-2 flex flex-col gap-y-1.5 text-center sm:text-left', props.class)">
        <slot />
    </div>
</template>
