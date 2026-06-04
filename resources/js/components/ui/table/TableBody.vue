<script setup lang="ts">
// The last row drops its hairline separator so the table ends on a clean edge.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tbody :class="cn('[&_tr:last-child]:border-0', props.class)">
        <slot />
    </tbody>
</template>
