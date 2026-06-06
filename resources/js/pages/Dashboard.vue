<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{
    name?: string;
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- TODO(#72): replace with the group-tile launcher (feature/ds-dashboard-tiles). -->
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1>Dashboard</h1>
        </div>
    </AppLayout>
</template>
