<script setup lang="ts">
import { launcherGrids } from '@/chrome/fixture';
import { isNodeVisible } from '@/chrome/gating';
import type { GroupNode } from '@/chrome/types';
import GroupTile from '@/components/GroupTile.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { PhUsersThree } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const page = usePage<SharedData>();

// The landing-page group-tile launcher (the legacy home grid). My Groups is
// server-built (PRD #209), the same prop that feeds the rail, so the two can never
// disagree about what the Member belongs to — pre-localized, omitted when they belong
// to none, each tile carrying the interim placeholder Group icon. The All Groups grid
// stays fixture-fed (super-tier gated via the show-all stub) until its slice (#211).
type LauncherGridView = { labelKey: string; items: GroupNode[]; localized: boolean };

const grids = computed<LauncherGridView[]>(() => {
    const out: LauncherGridView[] = [];

    const myGroups = page.props.rail.myGroups;
    if (myGroups) {
        out.push({
            labelKey: myGroups.labelKey,
            items: myGroups.items.map((item) => ({ ...item, icon: PhUsersThree })),
            localized: true,
        });
    }

    const allGroups = launcherGrids.find((grid) => grid.labelKey === 'nav.rail.all_groups');
    if (allGroups && isNodeVisible(allGroups)) {
        out.push({ labelKey: allGroups.labelKey, items: allGroups.items, localized: false });
    }

    return out;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <section v-for="grid in grids" :key="grid.labelKey">
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans(grid.labelKey) }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                    <GroupTile v-for="item in grid.items" :key="item.href" :item="item" :localized="grid.localized" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
