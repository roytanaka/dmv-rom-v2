<script setup lang="ts">
// DMV Settings › Stewardships (#841, ADR-0036 §7): the section exists so the switch works; its
// two cards (Member administration, Org-wide sender) land with #846.
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ groupsHref: string; stewardshipsHref: string }>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: props.groupsHref },
    { title: trans('dmv_settings.section.stewardships'), href: props.stewardshipsHref },
]);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">{{ trans('dmv_settings.subtitle') }}</p>
            </div>

            <DmvSettingsTabs />

            <p class="text-muted-foreground py-12 text-center text-base">{{ trans('dmv_settings.stewardships_soon') }}</p>
        </div>
    </AppLayout>
</template>
