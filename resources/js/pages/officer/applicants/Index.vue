<script setup lang="ts">
// The Applicants list (#928, ADR-0038 §8): Officer Tools → Members → Applicants. The status filter
// and the last-name search are query parameters; the server sends the rows, sorted by mentor then
// last name, each with whether this reader may edit it. Member administration reaches the page as
// the seventh Member list, so it keeps the Member list tabs. Add applicant shows on the New filter,
// as the Legacy app's. Below `md` the row's facts ride under the name (docs/conventions.md § Tables
// on small screens). Names are content, shown as stored.
import type { NavNode } from '@/chrome/types';
import ApplicantCodeDialog, { type ApplicantCodeSettings } from '@/components/applicants/ApplicantCodeDialog.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import RecordsSendEmail from '@/emailing/RecordsSendEmail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { PhMagnifyingGlass, PhPlus, PhPrinter } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

export interface ApplicantRow {
    id: number;
    first_name: string;
    last_name: string;
    status: string;
    placement: string | null;
    applied_on: string;
    last_update: string;
    email: string | null;
    mentor: string | null;
    can_edit: boolean;
}

const props = defineProps<{
    status: string;
    statuses: string[];
    search: string | null;
    canCreate: boolean;
    memberListTabs: { key: string; href: string }[] | null;
    applicantCode: ApplicantCodeSettings | null;
    applicants: {
        data: ApplicantRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('applicants.title'));
const listHref = computed(() => route('officer.members.list.applicants'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: listHref.value }]);
const tabItems = computed<NavNode[]>(() => (props.memberListTabs ?? []).map((tab) => ({ href: tab.href, labelKey: `records.list.${tab.key}` })));

const statusLabel = (row: ApplicantRow) =>
    row.placement ? `${trans(`applicants.status.${row.status}`)} · ${row.placement}` : trans(`applicants.status.${row.status}`);

const filter = ref(props.status);
const term = ref(props.search ?? '');
watch(
    () => [props.status, props.search],
    () => {
        filter.value = props.status;
        term.value = props.search ?? '';
    },
);

// Print list follows the filter and the search the page shows (#929).
const printHref = computed(() => {
    const query = new URLSearchParams();
    if (props.status !== 'all') {
        query.set('status', props.status);
    }
    if (props.search) {
        query.set('q', props.search);
    }
    const suffix = query.toString();
    return route('officer.members.applicants.print') + (suffix ? `?${suffix}` : '');
});

const reload = () => {
    const query: Record<string, string> = {};
    if (filter.value !== 'all') {
        query.status = filter.value;
    }
    if (term.value.trim() !== '') {
        query.q = term.value.trim();
    }
    router.get(listHref.value, query, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>
                <div class="flex flex-wrap gap-2">
                    <ApplicantCodeDialog v-if="applicantCode" :settings="applicantCode" />
                    <!-- Send email mails the Applicants the status filter shows; holder members only, as Add applicant. -->
                    <RecordsSendEmail
                        v-if="canCreate"
                        context="applicants"
                        :context-subject="status"
                        :label="trans('applicants.outputs.send_email')"
                    />
                    <Button variant="outline" as-child>
                        <Link :href="printHref">
                            <PhPrinter aria-hidden="true" />
                            {{ trans('applicants.outputs.print') }}
                        </Link>
                    </Button>
                    <Button v-if="canCreate && status === 'new'" as-child>
                        <Link :href="route('officer.members.applicants.create')">
                            <PhPlus aria-hidden="true" />
                            {{ trans('applicants.add') }}
                        </Link>
                    </Button>
                </div>
            </header>

            <p class="text-muted-foreground text-sm">{{ trans('applicants.overwritten_nightly') }}</p>

            <SectionTabs v-if="memberListTabs" :items="tabItems" variant="body" />

            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="applicant-status">{{ trans('applicants.filter') }}</Label>
                    <NativeSelect id="applicant-status" v-model="filter" @change="reload">
                        <option value="all">{{ trans('applicants.status.all') }}</option>
                        <option v-for="value in statuses" :key="value" :value="value">{{ trans(`applicants.status.${value}`) }}</option>
                    </NativeSelect>
                </div>
                <form class="flex max-w-md flex-1 items-end gap-2" role="search" @submit.prevent="reload">
                    <div class="flex flex-1 flex-col gap-1.5">
                        <Label for="applicant-search">{{ trans('applicants.search_last_name') }}</Label>
                        <Input id="applicant-search" v-model="term" type="search" autocomplete="off" />
                    </div>
                    <Button type="submit" variant="outline" :aria-label="trans('applicants.search')">
                        <PhMagnifyingGlass aria-hidden="true" />
                    </Button>
                </form>
            </div>

            <Table v-if="applicants.data.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('applicants.column.first_name') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.last_name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('applicants.column.status') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('applicants.column.applied_on') }}</TableHead>
                        <TableHead class="hidden lg:table-cell">{{ trans('applicants.column.last_update') }}</TableHead>
                        <TableHead class="hidden lg:table-cell">{{ trans('applicants.column.email') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('applicants.column.mentor') }}</TableHead>
                        <TableHead class="text-right">{{ trans('applicants.column.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="applicant in applicants.data" :key="applicant.id">
                        <TableCell class="font-medium">{{ applicant.first_name }}</TableCell>
                        <TableCell class="whitespace-normal">
                            <span class="font-medium">{{ applicant.last_name }}</span>
                            <span class="text-muted-foreground mt-1 flex flex-col gap-1 text-sm md:hidden">
                                <span>{{ statusLabel(applicant) }}</span>
                                <span>{{ applicant.mentor ?? trans('applicants.unassigned') }}</span>
                                <a v-if="applicant.email" :href="`mailto:${applicant.email}`" class="break-all">{{ applicant.email }}</a>
                            </span>
                        </TableCell>
                        <TableCell class="hidden whitespace-normal md:table-cell">{{ statusLabel(applicant) }}</TableCell>
                        <TableCell class="hidden md:table-cell">{{ formatDateOnly(applicant.applied_on, page.props.locale) }}</TableCell>
                        <TableCell class="hidden lg:table-cell">{{ formatDateOnly(applicant.last_update, page.props.locale) }}</TableCell>
                        <TableCell class="hidden lg:table-cell">
                            <a v-if="applicant.email" :href="`mailto:${applicant.email}`">{{ applicant.email }}</a>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <span v-if="applicant.mentor">{{ applicant.mentor }}</span>
                            <span v-else class="text-muted-foreground">{{ trans('applicants.unassigned') }}</span>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('officer.members.applicants.show', { applicant: applicant.id })">
                                        {{ trans(applicant.can_edit ? 'applicants.action.edit' : 'applicants.action.view') }}
                                    </Link>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p v-else class="text-muted-foreground py-12 text-center text-base">
                {{ trans(search ? 'applicants.list_no_match' : 'applicants.list_empty') }}
            </p>

            <nav v-if="applicants.last_page > 1" class="flex flex-wrap items-center justify-between gap-4" :aria-label="title">
                <Button v-if="applicants.prev_page_url" variant="outline" as-child>
                    <Link :href="applicants.prev_page_url" preserve-scroll>{{ trans('applicants.previous') }}</Link>
                </Button>
                <span v-else />
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('applicants.page', { current: String(applicants.current_page), last: String(applicants.last_page) }) }}
                </span>
                <Button v-if="applicants.next_page_url" variant="outline" as-child>
                    <Link :href="applicants.next_page_url" preserve-scroll>{{ trans('applicants.next') }}</Link>
                </Button>
                <span v-else />
            </nav>
        </div>
    </AppLayout>
</template>
