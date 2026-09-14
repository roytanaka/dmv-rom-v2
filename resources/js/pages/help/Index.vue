<script setup lang="ts">
// The help centre index (#517, PRD #516, ADR-0025). Lists the manifest's sections in
// order, each with its articles (the section overview first, then the task articles).
// Every logged-in Member sees every article — there is no role gating here (the
// Required-role badge lands in a later ticket). Section labels are chrome (ADR-0004),
// resolved via trans(); article titles and hrefs come from the server, already
// localized (ADR-0008).
import RequiredRoleBadge from '@/components/RequiredRoleBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface HelpIndexArticle {
    slug: string;
    title: string;
    requires: string[];
    href: string;
}

interface HelpIndexSection {
    key: string;
    labelKey: string;
    articles: HelpIndexArticle[];
}

defineProps<{
    sections: HelpIndexSection[];
}>();

// `computed` so the label survives a full-page locale switch — the messages load
// async, so a `trans()` snapshot taken at setup would capture the raw key.
const title = computed(() => trans('help.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('help') }]);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('help.intro') }}</p>
            </header>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-2">
                <h2 class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans(section.labelKey) }}</h2>
                <ul class="flex flex-col">
                    <li v-for="article in section.articles" :key="article.slug">
                        <Link
                            :href="article.href"
                            class="text-rom-ink hover:bg-muted -mx-2 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium"
                        >
                            {{ article.title }}
                            <RequiredRoleBadge :requires="article.requires" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
