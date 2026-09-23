<script setup lang="ts">
// A help article (#517, PRD #516, ADR-0025). Renders one article's server-sanitized
// HTML under its breadcrumb and title. The Markdown is rendered and sanitized on the
// server (HTML input stripped, unsafe links off), so the body is trusted chrome by
// the time it reaches v-html. The breadcrumb (Help › Section › Title) is resolved at
// the request locale on the server, so it needs no client-side translation. Tip and
// Note blockquotes arrive as `.callout` blocks carrying `data-callout` (#617).
// Previous and Next (#620) follow the manifest's published order across sections;
// their titles, hrefs and section labels arrive localized from the server.
import RequiredRoleBadge from '@/components/RequiredRoleBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { PhCaretLeft, PhCaretRight } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';

interface HelpNeighbour {
    title: string;
    href: string;
    section: string;
}

defineProps<{
    slug: string;
    title: string;
    html: string;
    requires: string[];
    breadcrumb: BreadcrumbItem[];
    previous: HelpNeighbour | null;
    next: HelpNeighbour | null;
}>();

// Two-line large outline buttons: the label and section on top, the title below.
const neighbourClass = 'h-auto w-full flex-col gap-1 py-3 whitespace-normal';
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
                <div class="text-rom-ink flex flex-col gap-4 text-base" v-html="html" />
            </article>

            <!-- Desktop: Previous left, Next right. Phone: stacked, Next first. -->
            <nav v-if="previous || next" class="grid max-w-2xl gap-3 sm:grid-cols-2">
                <Button v-if="previous" as-child variant="outline" size="lg" :class="[neighbourClass, 'items-start text-left']">
                    <Link :href="previous.href">
                        <span class="text-muted-foreground flex items-center gap-1 text-sm">
                            <PhCaretLeft aria-hidden="true" />
                            {{ trans('help.previous') }} · {{ previous.section }}
                        </span>
                        <span class="text-rom-ink font-semibold">{{ previous.title }}</span>
                    </Link>
                </Button>
                <Button
                    v-if="next"
                    as-child
                    variant="outline"
                    size="lg"
                    :class="[neighbourClass, 'order-first items-end text-right sm:order-none sm:col-start-2']"
                >
                    <Link :href="next.href">
                        <span class="text-muted-foreground flex items-center gap-1 text-sm">
                            {{ trans('help.next') }} · {{ next.section }}
                            <PhCaretRight aria-hidden="true" />
                        </span>
                        <span class="text-rom-ink font-semibold">{{ next.title }}</span>
                    </Link>
                </Button>
            </nav>
        </div>
    </AppLayout>
</template>

<style scoped>
/* The rendered Markdown carries no utility classes, so style it by element. No
   typography plugin in the project, and scoped @apply cannot see the theme's custom
   utilities (Tailwind v4), so these use the theme's CSS variables directly. */
.help-article :deep(h2) {
    margin-top: 0.5rem;
    color: var(--rom-ink);
    font-size: var(--text-lg);
    font-weight: 600;
}

.help-article :deep(p) {
    line-height: 1.625;
}

.help-article :deep(ol),
.help-article :deep(ul) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Steps: a square numbered marker in the primary colour beside each item. */
.help-article :deep(ol) {
    counter-reset: step;
    list-style: none;
}

.help-article :deep(ol > li) {
    position: relative;
    min-height: 1.75rem;
    padding-left: 2.5rem;
    counter-increment: step;
}

.help-article :deep(ol > li)::before {
    content: counter(step);
    position: absolute;
    top: 0;
    left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    background-color: var(--primary);
    color: var(--primary-foreground);
    font-size: var(--text-sm);
    font-weight: 600;
}

.help-article :deep(ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
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
    font-size: 0.875em;
}

.help-article :deep(blockquote) {
    border-left: 2px solid var(--border);
    padding-left: 0.75rem;
    color: var(--muted-foreground);
}

/* Tip and Note callouts, in the DesignNote look. The body is v-html, so the
   Phosphor icon (Lightbulb, Info) is its SVG path as a mask, painted rom-slate. */
.help-article :deep(.callout) {
    position: relative;
    border-left: 2px solid var(--rom-slate);
    background-color: var(--rom-slate-50);
    padding: 0.625rem 0.75rem 0.625rem 2.5rem;
}

.help-article :deep(.callout)::before {
    content: '';
    position: absolute;
    top: 0.9375rem;
    left: 0.75rem;
    width: 1.125rem;
    height: 1.125rem;
    background-color: var(--rom-slate);
    mask: var(--callout-icon) no-repeat center / contain;
}

.help-article :deep(.callout[data-callout='tip']) {
    --callout-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 256 256'%3E%3Cpath d='M176,232a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,232Zm40-128a87.55,87.55,0,0,1-33.64,69.21A16.24,16.24,0,0,0,176,186v6a16,16,0,0,1-16,16H96a16,16,0,0,1-16-16v-6a16,16,0,0,0-6.23-12.66A87.59,87.59,0,0,1,40,104.49C39.74,56.83,78.26,17.14,125.88,16A88,88,0,0,1,216,104Zm-16,0a72,72,0,0,0-73.74-72c-39,.92-70.47,33.39-70.26,72.39a71.65,71.65,0,0,0,27.64,56.3A32,32,0,0,1,96,186v6h64v-6a32.15,32.15,0,0,1,12.47-25.35A71.65,71.65,0,0,0,200,104Zm-16.11-9.34a57.6,57.6,0,0,0-46.56-46.55,8,8,0,0,0-2.66,15.78c16.57,2.79,30.63,16.85,33.44,33.45A8,8,0,0,0,176,104a9,9,0,0,0,1.35-.11A8,8,0,0,0,183.89,94.66Z'/%3E%3C/svg%3E");
}

.help-article :deep(.callout[data-callout='note']) {
    --callout-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 256 256'%3E%3Cpath d='M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z'/%3E%3C/svg%3E");
}

.help-article :deep(.callout > p:first-child > strong:first-child) {
    color: var(--rom-slate);
    font-weight: 600;
}

.help-article :deep(figure) {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.help-article :deep(figure img) {
    border: 1px solid var(--border);
}

.help-article :deep(figcaption) {
    color: var(--muted-foreground);
    font-size: var(--text-sm);
}
</style>
