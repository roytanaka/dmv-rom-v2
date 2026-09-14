<script setup lang="ts">
// A help article (#517, PRD #516, ADR-0025). Renders one article's server-sanitized
// HTML under its breadcrumb and title. The Markdown is rendered and sanitized on the
// server (HTML input stripped, unsafe links off), so the body is trusted chrome by
// the time it reaches v-html. The breadcrumb (Help › Section › Title) is resolved at
// the request locale on the server, so it needs no client-side translation.
import RequiredRoleBadge from '@/components/RequiredRoleBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    slug: string;
    title: string;
    html: string;
    requires: string[];
    breadcrumb: BreadcrumbItem[];
}>();
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumb">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <article class="help-article max-w-2xl">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <h1 class="text-rom-ink text-xl font-semibold">{{ title }}</h1>
                    <RequiredRoleBadge :requires="requires" />
                </div>
                <!-- eslint-disable-next-line vue/no-v-html -- body is server-sanitized chrome (ADR-0025) -->
                <div class="text-rom-ink flex flex-col gap-4 text-sm" v-html="html" />
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
/* The rendered Markdown carries no classes, so style it by element. No typography
   plugin in the project, and scoped @apply cannot see the theme's custom utilities
   (Tailwind v4), so these use the theme's CSS variables directly. */
.help-article :deep(h2) {
    margin-top: 0.5rem;
    color: var(--rom-ink);
    font-size: 1rem;
    font-weight: 600;
}

.help-article :deep(p) {
    line-height: 1.625;
}

.help-article :deep(ol),
.help-article :deep(ul) {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding-left: 1.25rem;
}

.help-article :deep(ol) {
    list-style-type: decimal;
}

.help-article :deep(ul) {
    list-style-type: disc;
}

.help-article :deep(a) {
    color: var(--rom-ink);
    text-decoration: underline;
}

.help-article :deep(code) {
    border-radius: 0.25rem;
    background-color: var(--muted);
    padding: 0.125rem 0.25rem;
    font-family: var(--font-mono, monospace);
    font-size: 0.75rem;
}

.help-article :deep(blockquote) {
    border-left: 2px solid var(--border);
    padding-left: 0.75rem;
    color: var(--muted-foreground);
}

.help-article :deep(figure) {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.help-article :deep(figure img) {
    border-radius: 0.375rem;
    border: 1px solid var(--border);
}

.help-article :deep(figcaption) {
    color: var(--muted-foreground);
    font-size: 0.75rem;
}
</style>
