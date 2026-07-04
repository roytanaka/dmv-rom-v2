<script setup lang="ts">
// A single launcher tile (PRD #209, #253): a fixed square holding the Group's colour
// mark, with the Group's name below, linking into its Group. The tile has no background
// of its own — each logo carries its own (see the logos README) and reads as a
// self-contained icon, so a tile chrome would only double it; a subtle background lifts
// in on hover for affordance. The logo is IDENTITY — the Group's own mark, or a single
// generic fallback when it has none, so the grid stays homogeneous (every tile a
// fixed-size mark). The label lives OUTSIDE the square so its length (and wrapping)
// never resizes the mark — every icon renders at the same size. The label is the
// Group's as-authored NAME (content — rendered verbatim in both locales, never
// translated; ADR-0004) and supplies the link's accessible name, so the mark itself is
// decorative (empty alt) to avoid a screen reader announcing the Group twice.
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
    <Link :href="href" class="group text-rom-ink flex flex-col items-center gap-1.5 text-center">
        <span
            class="group-hover:bg-rom-slate-100 group-focus-visible:ring-rom-slate-300 flex aspect-square w-full items-center justify-center rounded-none p-1 transition-colors group-focus-visible:ring-2 group-focus-visible:outline-none"
        >
            <img :src="src" alt="" class="object-contain" />
        </span>
        <span class="text-sm leading-tight font-medium">{{ item.name }}</span>
    </Link>
</template>
