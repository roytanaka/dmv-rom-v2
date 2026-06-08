<script setup lang="ts">
import { useClipboard } from '@/composables/useClipboard';
import { PhCheck, PhCopy } from '@phosphor-icons/vue';

// The shared click-to-copy affordance behind CopyButton and CodeSnippet: a button
// that copies `value`, flashes a check icon, and announces the copy to assistive
// tech via an aria-live region. Where the Clipboard API is unavailable it degrades
// to the `fallback` slot (plain text, or nothing). Consumers own the button's
// chrome via fall-through `class`/attrs and the icon sizing via `iconClass` /
// `copyIconClass`; the swap, the live region, and the support check live here once.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    value: string;
    label?: string;
    // Applied to both the check and copy icons (e.g. sizing).
    iconClass?: string;
    // Applied to the copy (idle) icon only — for muted/hover treatments.
    copyIconClass?: string;
}>();

const { copy, copied, isSupported } = useClipboard();
</script>

<template>
    <button v-if="isSupported" type="button" :aria-label="label ?? `Copy ${value}`" v-bind="$attrs" @click="copy(props.value)">
        <slot />
        <PhCheck v-if="copied" :class="['text-success', iconClass]" aria-hidden="true" />
        <PhCopy v-else :class="[iconClass, copyIconClass]" aria-hidden="true" />
        <span class="sr-only" aria-live="polite">{{ copied ? 'Copied to clipboard' : '' }}</span>
    </button>
    <slot v-else name="fallback" />
</template>
