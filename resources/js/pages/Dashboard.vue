<script setup lang="ts">
import { launcherGrids } from '@/chrome/fixture';
import { isNodeVisible } from '@/chrome/gating';
import GroupTile from '@/components/GroupTile.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// The landing-page group-tile launcher (the legacy home grid). My Groups always
// shows; the All Groups grid is gated to super-tier officers through the same
// show-all stub as the rail (visible for now — see chrome/gating.ts).
const grids = computed(() => launcherGrids.filter(isNodeVisible));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <section v-for="grid in grids" :key="grid.labelKey">
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans(grid.labelKey) }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                    <GroupTile v-for="item in grid.items" :key="item.href" :item="item" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
