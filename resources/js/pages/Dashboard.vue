<script setup lang="ts">
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

// The landing-page group-tile launcher (the legacy home grid). Both grids are
// server-built (PRD #209) from the same `rail` prop that feeds the rail, so the
// launcher and the rail can never disagree — pre-localized, each tile carrying the
// interim placeholder Group icon. My Groups is omitted when the Member belongs to no
// Group; All Groups is shown to every Member (the former super-tier gate is gone, #211),
// its top-level rows only — subcommittees stay in the rail.
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

    const allGroups = page.props.rail.allGroups;
    if (allGroups) {
        out.push({
            labelKey: allGroups.labelKey,
            items: allGroups.items.map((item) => ({ ...item, icon: PhUsersThree })),
            localized: true,
        });
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
