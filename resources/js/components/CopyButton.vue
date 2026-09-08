<script setup lang="ts">
import ClipboardCopy from '@/components/ClipboardCopy.vue';

// A click-to-copy affordance: renders its slot (a token name, value, or utility
// class) as a button that copies `value` and flashes a check. Where the Clipboard
// API is unavailable it degrades to plain, non-interactive text. Used across the
// /design-system gallery; the visual styling lives in the slotted content. The
// copy behaviour and a11y live in ClipboardCopy.
defineProps<{
    value: string;
    label?: string;
}>();
</script>

<template>
    <ClipboardCopy
        :value="value"
        :label="label"
        class="group/copy inline-flex max-w-full items-center gap-1.5 text-left"
        icon-class="size-3.5 flex-none"
        copy-icon-class="text-muted-foreground/40 group-hover/copy:text-muted-foreground transition-colors"
    >
        <span class="min-w-0 truncate"
            ><slot>{{ value }}</slot></span
        >
        <template #fallback>
            <span class="inline-flex max-w-full items-center"
                ><slot>{{ value }}</slot></span
            >
        </template>
    </ClipboardCopy>
</template>
