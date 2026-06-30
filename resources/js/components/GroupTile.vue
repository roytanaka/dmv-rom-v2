<script setup lang="ts">
// A single launcher tile — the legacy home-grid square: a solid black tile with a
// white Phosphor program icon and white label, linking into its Group. Square
// corners (ROM identity); the label is the Group's as-authored NAME (content —
// rendered verbatim in both locales, never translated; ADR-0004).
import type { GroupNode } from '@/chrome/types';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ item: GroupNode; localized?: boolean }>(), { localized: false });

// Tile hrefs are English-canonical in the fixture; localise to the active locale so
// launching a Group stays in-locale (ADR-0008). Server-built grids (PRD #209, My
// Groups) arrive pre-localized, so they pass `localized` to skip the client step.
const localizeHref = useLocalizedHref();
const href = computed(() => (props.localized ? props.item.href : localizeHref(props.item.href)));
</script>

<template>
    <Link
        :href="href"
        class="bg-rom-ink hover:bg-rom-ink-90 focus-visible:ring-rom-slate-300 flex aspect-square flex-col items-center justify-center gap-3 rounded-none p-4 text-center text-white transition-colors focus-visible:ring-2 focus-visible:outline-none"
    >
        <component :is="item.icon" v-if="item.icon" class="size-12 sm:size-14" />
        <span class="text-base font-medium">{{ item.name }}</span>
    </Link>
</template>
