<script setup lang="ts">
// My invitations (ADR-0034 §13), the tab the Invitations page opens on. Empty until a
// Member can be a Guest.
import InvitationTabs from '@/components/InvitationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

defineProps<{ can: { host: boolean } }>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: trans('invitations.title'), href: '#' }]);
</script>

<template>
    <Head :title="trans('invitations.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ trans('invitations.title') }}</h1>
            <InvitationTabs :can-host="can.host" />
            <p class="text-muted-foreground text-sm">{{ trans('invitations.mine.empty') }}</p>
        </div>
    </AppLayout>
</template>
