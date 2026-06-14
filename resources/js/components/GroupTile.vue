<script setup lang="ts">
// A single launcher tile — the legacy home-grid square: a solid black tile with a
// white Phosphor program icon and white label, linking into its Group. Square
// corners (ROM identity); the label is the Group's as-authored NAME (content —
// rendered verbatim in both locales, never translated; ADR-0004).
import type { GroupNode } from '@/chrome/types';
import { Link } from '@inertiajs/vue3';

defineProps<{ item: GroupNode }>();
</script>

<template>
    <Link
        :href="item.href"
        class="bg-rom-ink hover:bg-rom-ink-90 focus-visible:ring-rom-slate-300 flex aspect-square flex-col items-center justify-center gap-3 rounded-none p-4 text-center text-white transition-colors focus-visible:ring-2 focus-visible:outline-none"
    >
        <component :is="item.icon" v-if="item.icon" class="size-12 sm:size-14" />
        <span class="text-base font-medium">{{ item.name }}</span>
    </Link>
</template>
