<script setup lang="ts">
// The help centre index (#517, #518, #623, PRD #516, PRD #615, ADR-0025). A "Start
// here" box fed by the Getting started section, then one card per other published
// section: its title linked to its overview, the overview's lead line as the summary,
// up to three published task articles, and a closing link to all of them. Drafts
// never reach this page. Every logged-in Member sees every published article — the
// Required-role badge informs but does not gate. Section labels are chrome
// (ADR-0004), resolved via trans(); titles, leads and hrefs come from the server,
// already localized (ADR-0008).
import RequiredRoleBadge from '@/components/RequiredRoleBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface HelpLink {
    title: string;
    href: string;
}

interface HelpIndexArticle extends HelpLink {
    slug: string;
    requires: string[];
}

interface HelpIndexSection {
    key: string;
    labelKey: string;
    overview: HelpLink | null;
    lead: string | null;
    articles: HelpIndexArticle[];
    count: number;
}

defineProps<{
    startHere: HelpIndexSection | null;
    sections: HelpIndexSection[];
}>();

// `computed` so the label survives a full-page locale switch — the messages load
// async, so a `trans()` snapshot taken at setup would capture the raw key.
const title = computed(() => trans('help.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('help') }]);

// A section with only its overview reads "Read about <section>"; otherwise the link
// names all its task articles and how many there are.
function closingLabel(section: HelpIndexSection): string {
    const label = trans(section.labelKey);

    return section.count
        ? trans('help.all_articles', { section: label, count: String(section.count) })
        : trans('help.read_about', { section: label });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('help.intro') }}</p>
            </header>

            <section v-if="startHere?.overview" class="bg-rom-slate-50 border-rom-slate-300 flex flex-col items-start gap-3 border p-6">
                <h2 class="text-rom-ink text-lg font-semibold">{{ trans('help.start_here.heading') }}</h2>
                <p class="text-rom-ink text-base">{{ trans('help.start_here.line') }}</p>
                <Button as-child size="lg">
                    <Link :href="startHere.overview.href">{{ startHere.overview.title }}</Link>
                </Button>
                <TextLink v-for="article in startHere.articles" :key="article.slug" :href="article.href">{{ article.title }}</TextLink>
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="section in sections" :key="section.key" class="flex flex-col">
                    <CardHeader>
                        <CardTitle class="text-lg">
                            <TextLink v-if="section.overview" :href="section.overview.href">{{ trans(section.labelKey) }}</TextLink>
                            <template v-else>{{ trans(section.labelKey) }}</template>
                        </CardTitle>
                        <CardDescription v-if="section.lead" class="text-base">{{ section.lead }}</CardDescription>
                    </CardHeader>
                    <CardContent v-if="section.articles.length">
                        <ul class="flex flex-col gap-2">
                            <li v-for="article in section.articles" :key="article.slug" class="flex flex-wrap items-center gap-2">
                                <TextLink :href="article.href">{{ article.title }}</TextLink>
                                <RequiredRoleBadge :requires="article.requires" />
                            </li>
                        </ul>
                    </CardContent>
                    <CardFooter v-if="section.overview" class="mt-auto">
                        <TextLink :href="section.overview.href">{{ closingLabel(section) }}</TextLink>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
