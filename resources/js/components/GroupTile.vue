<script setup lang="ts">
// A single launcher tile (PRD #209, #253): a light card carrying the Group's colour
// logo above its name, linking into its Group. Square aspect and square corners (ROM
// identity). The logo is IDENTITY — the Group's own mark, or a single generic fallback
// when it has none, so the grid stays homogeneous (every tile a light card with a mark).
// The label is the Group's as-authored NAME (content — rendered verbatim in both
// locales, never translated; ADR-0004), and doubles as the logo's alt text so a screen
// reader announces the Group correctly regardless of locale.
import type { GroupNode } from '@/chrome/types';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { logoSrc } from '@/groups/logos';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ item: GroupNode; localized?: boolean }>(), { localized: false });

// Tile hrefs are English-canonical in the fixture; localise to the active locale so
// launching a Group stays in-locale (ADR-0008). Server-built grids (PRD #209, My
// Groups) arrive pre-localized, so they pass `localized` to skip the client step.
const localizeHref = useLocalizedHref();
const href = computed(() => (props.localized ? props.item.href : localizeHref(props.item.href)));

// The Group's own logo, or the generic fallback when its key is null/unknown.
const src = computed(() => logoSrc(props.item.logo));
</script>

<template>
    <Link
        :href="href"
        class="border-rom-slate-300/60 bg-rom-gray text-rom-ink hover:border-rom-slate-300 hover:bg-rom-slate-50 focus-visible:ring-rom-slate-300 flex aspect-square flex-col items-center justify-center gap-3 rounded-none border p-4 text-center transition-colors focus-visible:ring-2 focus-visible:outline-none"
    >
        <img :src="src" :alt="item.name" class="size-12 sm:size-14" />
        <span class="text-base font-medium">{{ item.name }}</span>
    </Link>
</template>
