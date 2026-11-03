<script setup lang="ts">
// The end of a Renewal Records follows up in person (#940, ADR-0038 §9): the Member chose Resign,
// Leave of Absence, or Sustaining from Active. The Renew link stays until Records acts.
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const title = computed(() => trans('renewal.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('renew') }]);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-2 p-4 sm:p-6">
            <h1 class="text-rom-ink text-2xl font-semibold">{{ trans('renewal.contact.heading') }}</h1>
            <p class="text-base">{{ trans('renewal.contact.body') }}</p>
        </div>
    </AppLayout>
</template>
