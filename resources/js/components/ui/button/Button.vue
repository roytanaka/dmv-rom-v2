<script setup lang="ts">
// ROM-tuned shadcn-vue Button. The styling lives in `buttonVariants` (./index.ts),
// not here: corners are squared (`rounded-none`) per the square-by-default
// convention, the size scale is bumped for the DMV's audience (sm 36 / default
// 40 / lg 48px, floored at 16px text), and the `link` variant carries the
// heritage-blue accent. See docs/conventions.md § Styling and the design system.
import { cn } from '@/lib/utils';
import { PhCircleNotch } from '@phosphor-icons/vue';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';
import { buttonVariants, type ButtonVariants } from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
    // While `loading`, the button shows a spinner and is disabled so a pending
    // action (form submit, etc.) can't be re-triggered. `disabled` is declared
    // so it can be merged with `loading` on the rendered element rather than
    // relying on attribute fall-through.
    disabled?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
});

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
        :disabled="isDisabled || undefined"
        :aria-busy="loading || undefined"
    >
        <!-- The spinner is the documented `rounded-full` exception (round by nature). -->
        <PhCircleNotch v-if="loading" class="animate-spin" aria-hidden="true" />
        <slot />
    </Primitive>
</template>
