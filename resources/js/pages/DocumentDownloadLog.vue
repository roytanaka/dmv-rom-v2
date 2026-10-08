<script setup lang="ts">
// The Document download log (#754, spec #290 story 55, ADR-0030): who opened which Document,
// and when, newest first, fifty to a page. Super-tier only and read-only. The filters are query
// parameters, so a filtered list has a URL: the selects and dates apply on change, the filename
// search on submit. A row outlives its Document, Member and Group: the filename is a snapshot,
// and the link and names drop away once their record is gone. Names are content, shown as stored.
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

interface DownloadRow {
    id: number;
    downloadedAt: string;
    memberName: string | null;
    groupName: string | null;
    filename: string;
    libraryHref: string | null;
}

interface Filters {
    group: number | null;
    member: number | null;
    from: string | null;
    to: string | null;
    q: string | null;
}

interface Option {
    value: number;
    label: string;
}

const props = defineProps<{
    downloads: {
        data: DownloadRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
    groups: Option[];
    members: Option[];
    listHref: string;
}>();

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('document_downloads.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: props.listHref }]);

// Download times read in the org timezone, like every other instant in the app.
const formatWhen = (iso: string): string =>
    new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: page.props.timezone }).format(new Date(iso));

// A Member or Group deleted since the download shows as such; the row itself stays.
const memberLabel = (row: DownloadRow): string => row.memberName ?? trans('document_downloads.deleted_member');
const groupLabel = (row: DownloadRow): string => row.groupName ?? trans('document_downloads.deleted_group');

const filtered = computed(() => Object.values(props.filters).some((value) => value !== null));

function applyFilters(filters: Filters): void {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== '') query[key] = String(value);
    }

    router.get(props.listHref, query, { preserveState: true, preserveScroll: true, replace: true });
}

// A select or date applies the moment it changes; an empty value clears that filter.
function setFilter(key: 'group' | 'member' | 'from' | 'to', value: string): void {
    const parsed = key === 'group' || key === 'member' ? (value === '' ? null : Number(value)) : value || null;
    applyFilters({ ...props.filters, [key]: parsed });
}

// The filename search applies on submit, so each keystroke is not a request.
const search = ref(props.filters.q ?? '');
watch(
    () => props.filters.q,
    (q) => (search.value = q ?? ''),
);
const submitSearch = (): void => applyFilters({ ...props.filters, q: search.value.trim() || null });

const clearFilters = (): void => applyFilters({ group: null, member: null, from: null, to: null, q: null });
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">{{ trans('document_downloads.subtitle') }}</p>
            </div>

            <form class="flex flex-wrap items-end gap-4" @submit.prevent="submitSearch">
                <div class="grid w-full gap-2 sm:w-56">
                    <Label for="downloads-filter-group">{{ trans('document_downloads.filter.group') }}</Label>
                    <NativeSelect
                        id="downloads-filter-group"
                        :model-value="filters.group === null ? '' : String(filters.group)"
                        @update:model-value="setFilter('group', String($event ?? ''))"
                    >
                        <option value="">{{ trans('document_downloads.filter.all_groups') }}</option>
                        <option v-for="group in groups" :key="group.value" :value="String(group.value)">{{ group.label }}</option>
                    </NativeSelect>
                </div>
                <div class="grid w-full gap-2 sm:w-56">
                    <Label for="downloads-filter-member">{{ trans('document_downloads.filter.member') }}</Label>
                    <NativeSelect
                        id="downloads-filter-member"
                        :model-value="filters.member === null ? '' : String(filters.member)"
                        @update:model-value="setFilter('member', String($event ?? ''))"
                    >
                        <option value="">{{ trans('document_downloads.filter.all_members') }}</option>
                        <option v-for="member in members" :key="member.value" :value="String(member.value)">{{ member.label }}</option>
                    </NativeSelect>
                </div>
                <div class="grid gap-2">
                    <Label for="downloads-filter-from">{{ trans('document_downloads.filter.from') }}</Label>
                    <Input
                        id="downloads-filter-from"
                        type="date"
                        :model-value="filters.from ?? ''"
                        @change="setFilter('from', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="downloads-filter-to">{{ trans('document_downloads.filter.to') }}</Label>
                    <Input
                        id="downloads-filter-to"
                        type="date"
                        :model-value="filters.to ?? ''"
                        @change="setFilter('to', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="grid w-full gap-2 sm:w-56">
                    <Label for="downloads-filter-search">{{ trans('document_downloads.filter.search') }}</Label>
                    <Input id="downloads-filter-search" v-model="search" type="search" />
                </div>
                <Button type="submit" variant="secondary">{{ trans('document_downloads.filter.apply') }}</Button>
                <Button v-if="filtered" type="button" variant="link" @click="clearFilters">{{ trans('document_downloads.filter.clear') }}</Button>
            </form>

            <div v-if="downloads.data.length" class="border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ trans('document_downloads.column.file') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">{{ trans('document_downloads.column.member') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">{{ trans('document_downloads.column.group') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">{{ trans('document_downloads.column.when') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in downloads.data" :key="row.id">
                            <TableCell class="whitespace-normal">
                                <!-- The Document's Folder, not its download: opening the file here would log this look. -->
                                <TextLink v-if="row.libraryHref" :href="row.libraryHref">{{ row.filename }}</TextLink>
                                <template v-else>
                                    {{ row.filename }}
                                    <span class="text-muted-foreground text-sm italic">({{ trans('document_downloads.deleted_document') }})</span>
                                </template>
                                <!-- On a phone the other columns hide; their facts ride under the name. -->
                                <p class="text-muted-foreground text-sm sm:hidden">
                                    {{ [memberLabel(row), groupLabel(row), formatWhen(row.downloadedAt)].join(' · ') }}
                                </p>
                            </TableCell>
                            <TableCell class="hidden sm:table-cell">
                                <template v-if="row.memberName">{{ row.memberName }}</template>
                                <span v-else class="text-muted-foreground italic">{{ memberLabel(row) }}</span>
                            </TableCell>
                            <TableCell class="hidden sm:table-cell">
                                <template v-if="row.groupName">{{ row.groupName }}</template>
                                <span v-else class="text-muted-foreground italic">{{ groupLabel(row) }}</span>
                            </TableCell>
                            <TableCell class="hidden tabular-nums sm:table-cell">{{ formatWhen(row.downloadedAt) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p v-else class="text-muted-foreground py-12 text-center text-base">
                {{ trans(filtered ? 'document_downloads.filtered_empty' : 'document_downloads.empty') }}
            </p>

            <nav v-if="downloads.last_page > 1" class="flex flex-wrap items-center justify-between gap-4" :aria-label="title">
                <Button v-if="downloads.prev_page_url" variant="outline" as-child>
                    <Link :href="downloads.prev_page_url" preserve-scroll>{{ trans('document_downloads.previous') }}</Link>
                </Button>
                <span v-else />
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('document_downloads.page', { current: String(downloads.current_page), last: String(downloads.last_page) }) }}
                </span>
                <Button v-if="downloads.next_page_url" variant="outline" as-child>
                    <Link :href="downloads.next_page_url" preserve-scroll>{{ trans('document_downloads.next') }}</Link>
                </Button>
                <span v-else />
            </nav>
        </div>
    </AppLayout>
</template>
