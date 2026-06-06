<script setup lang="ts">
import { PhCheckCircle, PhInfo, PhProhibit } from '@phosphor-icons/vue';
import { computed } from 'vue';

// A small tinted callout for the /design-system gallery, used to capture the
// *why* behind a non-obvious design choice. Three framings:
//   note  — neutral rationale (heritage-blue/info tint)
//   do    — the right call (success tint, check)
//   don't — the trap to avoid (destructive tint, prohibit)
// Square like every component; the leading icon and tint carry the meaning, so
// the title is optional.
const props = withDefaults(
    defineProps<{
        variant?: 'note' | 'do' | 'dont';
        title?: string;
    }>(),
    { variant: 'note' },
);

const tone = computed(() => {
    switch (props.variant) {
        case 'do':
            return { icon: PhCheckCircle, surface: 'border-success bg-success-bg', accent: 'text-success', label: 'Do' };
        case 'dont':
            return { icon: PhProhibit, surface: 'border-destructive bg-destructive-bg', accent: 'text-destructive', label: 'Don’t' };
        default:
            return { icon: PhInfo, surface: 'border-rom-slate bg-rom-slate-50', accent: 'text-rom-slate', label: 'Why' };
    }
});
</script>

<template>
    <div :class="['mt-4 flex max-w-2xl gap-3 border-l-2 px-3 py-2.5 text-sm', tone.surface]">
        <component :is="tone.icon" :class="['mt-0.5 size-4 flex-none', tone.accent]" aria-hidden="true" />
        <div class="min-w-0">
            <p :class="['font-semibold', tone.accent]">{{ title ?? tone.label }}</p>
            <p class="text-foreground/80 mt-0.5 leading-snug"><slot /></p>
        </div>
    </div>
</template>
