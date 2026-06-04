<script setup lang="ts">
// Generous 16px cell padding; the 18px row text is inherited from the Table
// default (`text-base`).
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <td :class="cn('p-4 align-middle [&:has([role=checkbox])]:pr-0', props.class)">
        <slot />
    </td>
</template>
