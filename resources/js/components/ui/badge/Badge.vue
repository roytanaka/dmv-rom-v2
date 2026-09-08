<script setup lang="ts">
// ROM-tuned shadcn-vue Badge. Tone taxonomy + the deliberate soft-`destructive`
// divergence live in `badgeVariants` (./index.ts). The optional `dot` renders a
// leading true circle (`rounded-full` — the documented exception to the
// square-by-default convention), tone-matched to the variant via `bg-current`
// so it inherits each variant's text colour (e.g. the solid success token on a
// success-bg chip). See docs/conventions.md § Styling and the design system.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import { badgeVariants, type BadgeVariants } from '.';

const props = defineProps<{
    variant?: BadgeVariants['variant'];
    dot?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span :class="cn(badgeVariants({ variant }), props.class)">
        <span v-if="dot" class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
        <slot />
    </span>
</template>
