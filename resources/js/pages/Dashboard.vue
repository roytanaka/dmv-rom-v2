<script setup lang="ts">
import type { GroupNode } from '@/chrome/types';
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

// The landing-page group-tile launcher (the legacy home grid). Server-built (PRD #209)
// from the same `rail` prop that feeds the rail, so the launcher and the rail can never
// disagree — pre-localized, each tile carrying the Group's logo key (PRD #253), which the
// tile resolves to its identity mark (or the generic fallback). Only My Groups is shown
// here (omitted when the Member belongs to no Group); Browse Groups lives in the rail, not
// on the Dashboard.
type LauncherGridView = { labelKey: string; items: GroupNode[] };

const myGroups = computed<LauncherGridView | null>(() => {
    const grid = page.props.rail.myGroups;
    return grid ? { labelKey: grid.labelKey, items: grid.items } : null;
});

// Stable v-for key: href when the tile has one, else its verbatim name.
const tileKey = (item: GroupNode) => item.href ?? item.name;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <section v-if="myGroups">
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans(myGroups.labelKey) }}</h2>
                <div class="grid grid-cols-4 gap-3 sm:grid-cols-5 sm:gap-4 lg:grid-cols-6 xl:grid-cols-8">
                    <GroupTile v-for="item in myGroups.items" :key="tileKey(item)" :item="item" :localized="true" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
