<script setup lang="ts">
// Group detail page (#188, PRD #186) — the "committee shell" every Group runs on,
// expressed once and reused for every Kind. A persistent header (full-bleed banner
// from the curated set with the Group name overlaid, the parent as a breadcrumb, a
// lifecycle badge), a sticky in-body section-tab strip (the first consumer of the
// section-tab relocation, ADR-0013 amendment), and the Overview tab.
//
// Roster (#189) and Meetings (#190) supply their own surfaces on top of this shell.
// The Group's name and About Us are member-authored content, rendered as-authored;
// everything else is translated chrome (ADR-0004). Officer edits to the Overview
// (#191) — inline About Us and banner selection — render only behind the server's
// `can.update` hint; the GroupPolicy enforces every mutation regardless.
import GroupMeetings from '@/components/GroupMeetings.vue';
import GroupRoster from '@/components/GroupRoster.vue';
import GroupScheduling from '@/components/GroupScheduling.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import type { NavNode } from '@/chrome/types';
import { bannerSources, defaultBannerKey, groupBannerKeys, groupBanners } from '@/groups/banners';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Meeting, type RosterMember, type RosterMeta, type Scheduling, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { PhImage, PhPencilSimple } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';

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
        banner_key: string | null;
        capabilities: { meetings: boolean; documents: boolean; scheduling: boolean; content: boolean; hours: boolean };
    };
    section: string;
    // UI hints from the policies — drive the officer affordances only; the server
    // enforces every mutation regardless. `update` gates the Overview edits (#191);
    // `createMeeting` gates the Meetings tab's "New meeting" control (#193);
    // `manageRoster` gates the Roster tab's officer CRUD (#192);
    // `createSchedule` gates the Scheduling tab's "New schedule" control (#354).
    can: { update: boolean; createMeeting: boolean; manageRoster: boolean; createSchedule: boolean };
    roster: RosterMember[];
    rosterMeta: RosterMeta;
    meetings: Meeting[];
    scheduling: Scheduling;
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
        { href: href('roster'), labelKey: 'group.tab.members' },
    ];
    if (props.group.capabilities.meetings) list.push({ href: href('meetings'), labelKey: 'group.tab.meetings' });
    if (props.group.capabilities.documents) list.push({ href: href('documents'), labelKey: 'group.tab.documents', soon: true });
    if (props.group.capabilities.scheduling) list.push({ href: href('scheduling'), labelKey: 'group.tab.scheduling' });
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

// The selected banner's AVIF + JPG sources. An unset (or unknown) banner_key
// falls back to the default banner (Rotunda); the heritage gradient stays as the
// base layer underneath, showing through only while the image loads.
const banner = computed(() => bannerSources(props.group.banner_key) ?? groupBanners[defaultBannerKey]);

// Officer edits (#191) — gated entirely by `can.update`; the controls below only
// render when the server says so. Two independent forms hit `groups.update`, each
// sending exactly the field it owns so an unsaved About edit never rides along
// with a banner pick and vice versa.
const updateUrl = () => route('groups.update', { group: props.group.slug });

const editingAbout = ref(false);
const aboutForm = useForm<{ description: string | null }>({ description: props.overview.description });
// Bridge the nullable form field to the Textarea (which takes string|number):
// an emptied field persists as null so the Overview shows its "no description" state.
const aboutDraft = computed({
    get: () => aboutForm.description ?? '',
    set: (value: string) => (aboutForm.description = value === '' ? null : value),
});
const saveAbout = () =>
    aboutForm.patch(updateUrl(), {
        preserveScroll: true,
        onSuccess: () => (editingAbout.value = false),
    });
const cancelAbout = () => {
    aboutForm.description = props.overview.description;
    editingAbout.value = false;
};

const bannerPickerOpen = ref(false);
const bannerForm = useForm<{ banner_key: string | null }>({ banner_key: props.group.banner_key });
const pickBanner = (key: string | null) => {
    bannerForm.banner_key = key;
    bannerForm.patch(updateUrl(), {
        preserveScroll: true,
        onSuccess: () => (bannerPickerOpen.value = false),
    });
};
</script>

<template>
    <Head :title="group.name" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col">
            <!-- Persistent header — a full-bleed banner from the curated set (#191),
                 the Group name on a dark bottom gradient, the parent breadcrumb, and
                 the lifecycle badge. The heritage gradient shows through as the
                 neutral default when no banner is set. No Kind badge (PRD #186). -->
            <header class="from-rom-ink to-rom-slate-700 relative isolate flex h-44 items-end overflow-hidden bg-gradient-to-br sm:h-52 lg:h-56">
                <picture v-if="banner">
                    <source :srcset="banner.avif" type="image/avif" />
                    <img :src="banner.jpg" :alt="trans('group.banner.aria')" class="absolute inset-0 h-full w-full object-cover" />
                </picture>
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/10"></div>

                <!-- Officer affordance: pick the Group's banner from the curated set. -->
                <Dialog v-if="can.update" v-model:open="bannerPickerOpen">
                    <DialogTrigger as-child>
                        <Button variant="secondary" size="sm" class="absolute top-4 right-4 z-10 gap-1.5">
                            <PhImage class="h-4 w-4" />
                            {{ trans('group.edit.banner') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{{ trans('group.edit.banner_title') }}</DialogTitle>
                        </DialogHeader>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <!-- The 6 curated landmarks. An unset Group defaults to Rotunda,
                                 so the Rotunda swatch reads as selected when banner_key is null. -->
                            <button
                                v-for="key in groupBannerKeys"
                                :key="key"
                                type="button"
                                class="focus-visible:ring-rom-slate flex flex-col items-stretch gap-1.5 focus-visible:ring-2 focus-visible:outline-none"
                                :disabled="bannerForm.processing"
                                @click="pickBanner(key)"
                            >
                                <picture
                                    class="block h-16 w-full border-2"
                                    :class="(group.banner_key ?? defaultBannerKey) === key ? 'border-rom-slate' : 'border-transparent'"
                                >
                                    <source :srcset="groupBanners[key].avif" type="image/avif" />
                                    <img :src="groupBanners[key].jpg" alt="" class="h-full w-full object-cover" />
                                </picture>
                                <span class="text-muted-foreground text-xs">{{ trans(`group.banner.option.${key}`) }}</span>
                            </button>
                        </div>
                    </DialogContent>
                </Dialog>

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
            <div class="bg-background sticky top-16 z-20 px-4 py-4 shadow-sm sm:px-6">
                <SectionTabs :items="tabs" variant="body" :soon-label="trans('group.soon')" />
            </div>

            <div class="flex-1 p-4 sm:p-6">
                <!-- Overview — two-column, read-only. Main: About Us + child Groups.
                     Sidebar: leadership at a glance + the quiet facts card. Contact
                     details are deliberately omitted from this surface (PRD #186). -->
                <div v-if="section === 'overview'" class="grid gap-6 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0">
                                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('group.about') }}</CardTitle>
                                <Button v-if="can.update && !editingAbout" variant="ghost" size="sm" class="gap-1.5" @click="editingAbout = true">
                                    <PhPencilSimple class="h-4 w-4" />
                                    {{ trans('group.edit.about') }}
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <form v-if="editingAbout" class="flex flex-col gap-3" @submit.prevent="saveAbout">
                                    <Textarea
                                        v-model="aboutDraft"
                                        :rows="6"
                                        :placeholder="trans('group.edit.about_placeholder')"
                                        :aria-label="trans('group.about')"
                                    />
                                    <div class="flex gap-2">
                                        <Button type="submit" size="sm" :disabled="aboutForm.processing">{{ trans('group.edit.save') }}</Button>
                                        <Button type="button" variant="ghost" size="sm" :disabled="aboutForm.processing" @click="cancelAbout">
                                            {{ trans('group.edit.cancel') }}
                                        </Button>
                                    </div>
                                </form>
                                <template v-else>
                                    <p v-if="overview.description" class="text-rom-ink text-base whitespace-pre-line">{{ overview.description }}</p>
                                    <p v-else class="text-muted-foreground text-sm">{{ trans('group.about_empty') }}</p>
                                </template>
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

                <!-- Roster (#189) — the Group-scoped Directory surface, with officer
                     CRUD (#192) behind the `can.manageRoster` hint. -->
                <GroupRoster
                    v-else-if="section === 'roster'"
                    :members="roster"
                    :can-manage="can.manageRoster"
                    :meta="rosterMeta"
                    :group-slug="group.slug"
                />

                <!-- Meetings (#190, #193) — the Group's first own-data, members-only
                     surface, with officer CRUD behind the `can` hints. -->
                <GroupMeetings v-else-if="section === 'meetings'" :meetings="meetings" :can-create="can.createMeeting" :group-slug="group.slug" />

                <!-- Scheduling (#353, #354) — the Schedule read surface (a list, an
                     empty state, or a single Schedule opened directly, org-open) with
                     the Scheduler's inline authoring gated by `can.createSchedule`. -->
                <GroupScheduling
                    v-else-if="section === 'scheduling'"
                    :scheduling="scheduling"
                    :can-create="can.createSchedule"
                    :group-slug="group.slug"
                />

                <!-- The capability stubs fill in later slices. -->
                <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.coming_soon') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
