<script setup lang="ts">
// Shared placeholder for the route stubs (#109). Every stubbed Zone A / Zone C /
// dynamic-group route renders this one page until the real feature lands. The
// optional `group` prop is the as-authored slug echoed by the dynamic group
// route (no model lookup — ADR-0008); it is shown verbatim, never translated.
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    group?: string;
    section?: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('placeholder.coming_soon.title'), href: '#' }];

const groupLine = computed(() => [props.group, props.section].filter(Boolean).join(' / '));
</script>

<template>
    <Head :title="trans('placeholder.coming_soon.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
            <h1 class="text-rom-ink text-lg font-semibold">{{ trans('placeholder.coming_soon.title') }}</h1>
            <p class="text-muted-foreground max-w-md text-sm">{{ trans('placeholder.coming_soon.body') }}</p>
            <!-- As-authored Group slug, rendered verbatim (content, not chrome). -->
            <p v-if="groupLine" class="text-muted-foreground font-mono text-xs">{{ groupLine }}</p>
        </div>
    </AppLayout>
</template>
