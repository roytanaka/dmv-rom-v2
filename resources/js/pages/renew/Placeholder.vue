<script setup lang="ts">
// Steps 2 to 4 of the Renewal (#939, ADR-0038 §9) until the form (#940), skills and payment (#941)
// replace this page, one step at a time.
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    step: 'form' | 'skills' | 'payment';
}>();

const title = computed(() => trans('renewal.title'));
const stepTitle = computed(() => trans(`renewal.step.${props.step}`));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: route('renew') },
    { title: stepTitle.value, href: '#' },
]);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
            <h1 class="text-rom-ink text-lg font-semibold">{{ stepTitle }}</h1>
            <p class="text-muted-foreground max-w-md text-sm">{{ trans('placeholder.coming_soon.body') }}</p>
        </div>
    </AppLayout>
</template>
