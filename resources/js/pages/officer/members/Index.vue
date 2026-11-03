<script setup lang="ts">
// The Member lists (#919, ADR-0038 §5): Officer Tools → Members. Seven tabs, one route; the server
// sends the rows, the list's row actions and its Send email answer. The search is a query
// parameter, applied on submit. Edit opens the Member record; Reinstate posts (#920). Photo, Delete
// and Send email stay disabled until their tickets wire them. Below `md` the row's facts ride under the name
// (docs/conventions.md § Tables on small screens). Names are content, shown as stored.
import type { NavNode } from '@/chrome/types';
import SectionTabs from '@/components/SectionTabs.vue';
import StandingBadge from '@/components/StandingBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { PhEnvelopeSimple, PhMagnifyingGlass } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

interface Row {
    id: number;
    first_name: string;
    last_name: string;
    standing: string;
    email: string | null;
    phone: string | null;
    last_update: string | null;
}

const props = defineProps<{
    list: string;
    tabs: { key: string; href: string }[];
    actions: string[];
    canSendEmail: boolean;
    search: string | null;
    members: {
        data: Row[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('records.members'));
const listLabel = computed(() => trans(`records.list.${props.list}`));
const listHref = computed(() => props.tabs.find((tab) => tab.key === props.list)?.href ?? props.tabs[0].href);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: listHref.value }]);
const tabItems = computed<NavNode[]>(() => props.tabs.map((tab) => ({ href: tab.href, labelKey: `records.list.${tab.key}` })));

// The actions a later ticket wires; each renders disabled until then.
const pendingActions = computed(() => props.actions.filter((action) => action !== 'edit' && action !== 'reinstate'));
const canReinstate = computed(() => props.actions.includes('reinstate'));

// Reinstate (#920): Reinstate then Active, dated today; the Member leaves this list.
const reinstate = (id: number) => {
    router.post(route('members.reinstate', { member: id }), {}, { preserveScroll: true });
};

const term = ref(props.search ?? '');
watch(
    () => props.search,
    (value) => (term.value = value ?? ''),
);

const submitSearch = () => {
    const q = term.value.trim();
    router.get(listHref.value, q === '' ? {} : { q }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>
                <Button v-if="canSendEmail" variant="outline" disabled>
                    <PhEnvelopeSimple aria-hidden="true" />
                    {{ trans('records.send_email') }}
                </Button>
            </header>

            <SectionTabs :items="tabItems" variant="body" />

            <form class="flex max-w-md items-end gap-2" role="search" @submit.prevent="submitSearch">
                <div class="flex flex-1 flex-col gap-1.5">
                    <Label for="member-list-search">{{ trans('records.search_last_name') }}</Label>
                    <Input id="member-list-search" v-model="term" type="search" autocomplete="off" />
                </div>
                <Button type="submit" variant="outline" :aria-label="trans('records.search')">
                    <PhMagnifyingGlass aria-hidden="true" />
                </Button>
            </form>

            <Table v-if="members.data.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('records.column.name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.category') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.email') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.phone') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.last_update') }}</TableHead>
                        <TableHead class="text-right">{{ trans('records.column.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="member in members.data" :key="member.id">
                        <TableCell class="whitespace-normal">
                            <span class="font-medium">{{ member.last_name }}, {{ member.first_name }}</span>
                            <span class="text-muted-foreground mt-1 flex flex-col gap-1 text-sm md:hidden">
                                <StandingBadge :standing="member.standing" class="w-fit" />
                                <a v-if="member.email" :href="`mailto:${member.email}`" class="break-all">{{ member.email }}</a>
                                <a v-if="member.phone" :href="`tel:${member.phone}`">{{ member.phone }}</a>
                            </span>
                        </TableCell>
                        <TableCell class="hidden md:table-cell"><StandingBadge :standing="member.standing" /></TableCell>
                        <TableCell class="hidden md:table-cell"
                            ><a v-if="member.email" :href="`mailto:${member.email}`">{{ member.email }}</a></TableCell
                        >
                        <TableCell class="hidden md:table-cell"
                            ><a v-if="member.phone" :href="`tel:${member.phone}`">{{ member.phone }}</a></TableCell
                        >
                        <TableCell class="hidden md:table-cell">
                            {{ member.last_update ? formatDateOnly(member.last_update, page.props.locale) : '' }}
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('officer.members.record', { member: member.id })">{{ trans('records.action.edit') }}</Link>
                                </Button>
                                <Button v-if="canReinstate" variant="outline" @click="reinstate(member.id)">
                                    {{ trans('records.action.reinstate') }}
                                </Button>
                                <Button v-for="action in pendingActions" :key="action" variant="outline" disabled>
                                    {{ trans(`records.action.${action}`) }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p v-else class="text-muted-foreground py-12 text-center text-base">
                {{ trans(search ? 'records.list_no_match' : 'records.list_empty') }}
            </p>

            <nav v-if="members.last_page > 1" class="flex flex-wrap items-center justify-between gap-4" :aria-label="listLabel">
                <Button v-if="members.prev_page_url" variant="outline" as-child>
                    <Link :href="members.prev_page_url" preserve-scroll>{{ trans('records.previous') }}</Link>
                </Button>
                <span v-else />
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('records.page', { current: String(members.current_page), last: String(members.last_page) }) }}
                </span>
                <Button v-if="members.next_page_url" variant="outline" as-child>
                    <Link :href="members.next_page_url" preserve-scroll>{{ trans('records.next') }}</Link>
                </Button>
                <span v-else />
            </nav>
        </div>
    </AppLayout>
</template>
