<script setup lang="ts">
import { useClipboard } from '@/composables/useClipboard';
import { PhCheck, PhCopy } from '@phosphor-icons/vue';

// A click-to-copy affordance: renders its slot (a token name, value, or utility
// class) as a button that copies `value` and flashes a check. Where the Clipboard
// API is unavailable it degrades to plain, non-interactive text. Used across the
// /design-system gallery; the visual styling lives in the slotted content.
const props = defineProps<{
    value: string;
    label?: string;
}>();

const { copy, copied, isSupported } = useClipboard();
</script>

<template>
    <button
        v-if="isSupported"
        type="button"
        class="group/copy inline-flex max-w-full cursor-pointer items-center gap-1.5 text-left"
        :aria-label="label ?? `Copy ${value}`"
        @click="copy(props.value)"
    >
        <span class="min-w-0 truncate"
            ><slot>{{ value }}</slot></span
        >
        <PhCheck v-if="copied" class="text-success size-3.5 flex-none" aria-hidden="true" />
        <PhCopy
            v-else
            class="text-muted-foreground/40 group-hover/copy:text-muted-foreground size-3.5 flex-none transition-colors"
            aria-hidden="true"
        />
        <span class="sr-only" aria-live="polite">{{ copied ? 'Copied to clipboard' : '' }}</span>
    </button>
    <span v-else class="inline-flex max-w-full items-center"
        ><slot>{{ value }}</slot></span
    >
</template>
