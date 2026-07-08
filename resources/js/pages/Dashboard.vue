<script setup lang="ts">
import type { GroupNode, PeerNode } from '@/chrome/types';
import GroupTile from '@/components/GroupTile.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const page = usePage<SharedData>();

// The landing-page group-tile launcher (the legacy home grid). Both grids are
// server-built (PRD #209) from the same `rail` prop that feeds the rail, so the
// launcher and the rail can never disagree — pre-localized, each tile carrying the
// Group's logo key (PRD #253), which the tile resolves to its identity mark (or the
// generic fallback). My Groups is omitted when the Member belongs to no Group; Other
// Groups is shown to every Member, its top-level rows only — the four container peers
// (ADR-0020 §C), whose nested Groups stay in the rail.
type LauncherGridView = { labelKey: string; items: Array<GroupNode | PeerNode>; localized: boolean };

const grids = computed<LauncherGridView[]>(() => {
    const out: LauncherGridView[] = [];

    const myGroups = page.props.rail.myGroups;
    if (myGroups) {
        out.push({ labelKey: myGroups.labelKey, items: myGroups.items, localized: true });
    }

    const otherGroups = page.props.rail.otherGroups;
    if (otherGroups) {
        out.push({ labelKey: otherGroups.labelKey, items: otherGroups.items, localized: true });
    }

    return out;
});

// Stable v-for key: href when the tile has one, else labelKey (container peers omit
// href, PRD #289) or name — mirroring NavRail's nodeKey so keys never collide on undefined.
const tileKey = (item: GroupNode | PeerNode) => item.href ?? ('name' in item ? item.name : item.labelKey);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <section v-for="grid in grids" :key="grid.labelKey">
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans(grid.labelKey) }}</h2>
                <div class="grid grid-cols-4 gap-3 sm:grid-cols-5 sm:gap-4 lg:grid-cols-6 xl:grid-cols-8">
                    <GroupTile v-for="item in grid.items" :key="tileKey(item)" :item="item" :localized="grid.localized" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
