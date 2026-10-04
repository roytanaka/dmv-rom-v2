<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div data-slot="card" :class="cn('rounded-none border bg-card text-card-foreground shadow-xs', props.class)">
        <slot />
    </div>
</template>
