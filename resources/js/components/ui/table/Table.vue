<script setup lang="ts">
// ROM-tuned shadcn-vue Table. The ROM listing identity is baked into the
// defaults across the upstream parts (Table / TableHeader / TableRow /
// TableHead / TableCell) — class strings restyled in place, structure
// untouched, so registry updates stay mergeable. Here on the <table>: the 2px
// black top rule (`border-t-2 border-primary`, --primary is pure black) and
// 18px row text (`text-base`); TableHead overrides to the 12px-class uppercase
// heads. See docs/conventions.md § Styling and the design system.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div class="relative w-full overflow-auto">
        <table :class="cn('w-full caption-bottom border-t-2 border-primary text-base', props.class)">
            <slot />
        </table>
    </div>
</template>
