<script setup lang="ts">
// DMV Settings › one Group (#841, ADR-0036 §3–§6): the structure form's page. The route exists so
// the tree can link to it and #842 can redirect here after a create; the form itself lands with
// #843. Breadcrumbs: DMV Settings › {Group name}; the slug sits under the h1 as muted mono.
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    group: { id: number; slug: string; name: string };
    groupsHref: string;
    stewardshipsHref: string;
}>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: props.groupsHref },
    { title: props.group.name, href: '#' },
]);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }}</h1>
                <p class="text-muted-foreground mt-1 font-mono text-sm">/groups/{{ group.slug }}</p>
            </div>

            <DmvSettingsTabs />

            <p class="text-muted-foreground py-12 text-center text-base">{{ trans('dmv_settings.structure_soon') }}</p>
        </div>
    </AppLayout>
</template>
