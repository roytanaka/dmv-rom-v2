<script setup lang="ts">
// Group detail page (#188, PRD #186) — the "committee shell" every Group runs on,
// expressed once and reused for every Kind. A persistent header (full-bleed default
// banner with the Group name overlaid, the parent as a breadcrumb, a lifecycle
// badge), a sticky in-body section-tab strip (the first consumer of the section-tab
// relocation, ADR-0013 amendment), and the read-only Overview tab.
//
// This slice ships Overview only; Roster (#189) and Meetings (#190) replace the
// "coming soon" panel with their own surfaces on top of this shell. The Group's name
// and About Us are member-authored content, rendered as-authored; everything else is
// translated chrome (ADR-0004). All data arrives as props — no authority is computed
// here, and there is no edit affordance (officer edits land in #191).
import SectionTabs from '@/components/SectionTabs.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { NavNode } from '@/chrome/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface Parent {
    name: string;
    slug: string;
}

interface ChildGroup {
    name: string;
    slug: string;
}

interface LeadershipEntry {
    role: string;
    member_id: number;
    name: string;
}

interface Facts {
    member_count: number;
    meets: boolean;
    time_boxed: boolean;
    start_date: string | null;
    end_date: string | null;
}

const props = defineProps<{
    group: {
        id: number;
        name: string;
        slug: string;
        archived: boolean;
        end_date: string | null;
        parent: Parent | null;
        capabilities: { meetings: boolean; documents: boolean; scheduling: boolean; content: boolean; hours: boolean };
    };
    section: string;
    overview: {
        description: string | null;
        children: ChildGroup[];
        leadership: LeadershipEntry[];
        facts: Facts;
    };
}>();

const page = usePage<SharedData>();
const formatDate = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'long' }).format(new Date(iso));

// Lifecycle badge: Archived takes precedence; otherwise a time-boxed Group whose
// window has closed reads "Ended <date>". An open or open-ended Group shows none.
const ended = computed(() => !props.group.archived && props.group.end_date !== null && new Date(props.group.end_date) < new Date());

// The in-body section tabs. Overview · Roster are always present; Meetings is a real
// tab when the Group runs meetings. The remaining capabilities render as muted "soon"
// stubs only when their flag is on — the feature itself lands in a later slice. Hrefs
// are English-canonical; SectionTabs localises them to the active locale (ADR-0008).
const tabs = computed<NavNode[]>(() => {
    const href = (section?: string) => (section ? `/groups/${props.group.slug}/${section}` : `/groups/${props.group.slug}`);
    const list: NavNode[] = [
        { href: href(), labelKey: 'group.tab.overview' },
        { href: href('roster'), labelKey: 'group.tab.roster' },
    ];
    if (props.group.capabilities.meetings) list.push({ href: href('meetings'), labelKey: 'group.tab.meetings' });
    if (props.group.capabilities.documents) list.push({ href: href('documents'), labelKey: 'group.tab.documents', soon: true });
    if (props.group.capabilities.scheduling) list.push({ href: href('scheduling'), labelKey: 'group.tab.scheduling', soon: true });
    if (props.group.capabilities.content) list.push({ href: href('content'), labelKey: 'group.tab.content', soon: true });
    if (props.group.capabilities.hours) list.push({ href: href('hours'), labelKey: 'group.tab.hours', soon: true });
    return list;
});

// The time-boxed dates fact, phrased by which ends are known.
const datesFact = computed(() => {
    const { start_date, end_date } = props.overview.facts;
    if (start_date && end_date) return trans('group.facts_dates', { start: formatDate(start_date), end: formatDate(end_date) });
    if (start_date) return trans('group.facts_starts', { date: formatDate(start_date) });
    if (end_date) return trans('group.facts_ends', { date: formatDate(end_date) });
    return null;
});
</script>

<template>
    <Head :title="group.name" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col">
            <!-- Persistent header — full-bleed default banner (a curated set + per-Group
                 selection lands in #191), the Group name on a dark bottom gradient, the
                 parent breadcrumb, and the lifecycle badge. No Kind badge (PRD #186). -->
            <header class="from-rom-ink to-rom-slate-700 relative isolate flex h-44 items-end overflow-hidden bg-gradient-to-br sm:h-52 lg:h-56">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                <div class="relative z-10 flex w-full flex-col gap-1.5 p-4 sm:p-6">
                    <nav v-if="group.parent" aria-label="Breadcrumb" class="text-sm text-white/80">
                        <TextLink
                            :href="route('groups.show', { group: group.parent.slug })"
                            class="text-white/80 decoration-white/40 hover:text-white"
                        >
                            {{ group.parent.name }}
                        </TextLink>
                    </nav>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-semibold text-white sm:text-4xl">{{ group.name }}</h1>
                        <Badge v-if="group.archived" variant="secondary">{{ trans('group.archived') }}</Badge>
                        <Badge v-else-if="ended && group.end_date" variant="warning">
                            {{ trans('group.ended', { date: formatDate(group.end_date) }) }}
                        </Badge>
                    </div>
                </div>
            </header>

            <!-- Sticky in-body section-tab strip, directly under the header (ADR-0013
                 amendment). Reuses SectionTabs in its 'body' placement. -->
            <div class="bg-background border-border sticky top-16 z-20 border-b px-4 sm:px-6">
                <SectionTabs :items="tabs" variant="body" :soon-label="trans('group.soon')" />
            </div>

            <div class="flex-1 p-4 sm:p-6">
                <!-- Overview — two-column, read-only. Main: About Us + child Groups.
                     Sidebar: leadership at a glance + the quiet facts card. Contact
                     details are deliberately omitted from this surface (PRD #186). -->
                <div v-if="section === 'overview'" class="grid gap-6 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('group.about') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p v-if="overview.description" class="text-rom-ink text-base whitespace-pre-line">{{ overview.description }}</p>
                                <p v-else class="text-muted-foreground text-sm">{{ trans('group.about_empty') }}</p>
                            </CardContent>
                        </Card>

                        <Card v-if="overview.children.length">
                            <CardHeader>
                                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('group.children') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul class="flex flex-col gap-2 text-base">
                                    <li v-for="child in overview.children" :key="child.slug">
                                        <TextLink :href="route('groups.show', { group: child.slug })" class="font-medium">{{ child.name }}</TextLink>
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('group.leadership') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul v-if="overview.leadership.length" class="flex flex-col gap-3 text-sm">
                                    <li v-for="entry in overview.leadership" :key="`${entry.role}-${entry.member_id}`" class="flex flex-col gap-0.5">
                                        <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                            {{ trans(`group.role.${entry.role}`) }}
                                        </span>
                                        <TextLink :href="route('members.show', { member: entry.member_id })" class="font-medium">{{
                                            entry.name
                                        }}</TextLink>
                                    </li>
                                </ul>
                                <p v-else class="text-muted-foreground text-sm">{{ trans('group.leadership_empty') }}</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('group.facts') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul class="text-rom-ink flex flex-col gap-2 text-sm">
                                    <li>
                                        {{ transChoice('group.facts_members', overview.facts.member_count) }}
                                    </li>
                                    <li v-if="overview.facts.meets">{{ trans('group.facts_meets') }}</li>
                                    <li v-if="overview.facts.time_boxed && datesFact">{{ datesFact }}</li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Roster (#189) / Meetings (#190) fill these panels in later slices. -->
                <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.coming_soon') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
