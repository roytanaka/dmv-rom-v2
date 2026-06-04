<script setup lang="ts">
// ROM-tuned shadcn-vue Button. The styling lives in `buttonVariants` (./index.ts),
// not here: corners are squared (`rounded-none`) per the square-by-default
// convention, the size scale is bumped for the DMV's audience (sm 36 / default
// 40 / lg 48px, floored at 15px text), and the `link` variant carries the
// heritage-blue accent. See docs/conventions.md § Styling and the design system.
import { cn } from '@/lib/utils';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import type { HTMLAttributes } from 'vue';
import { buttonVariants, type ButtonVariants } from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
});
</script>

<template>
    <Primitive :as="as" :as-child="asChild" :class="cn(buttonVariants({ variant, size }), props.class)">
        <slot />
    </Primitive>
</template>
