<script setup lang="ts">
// Print list (#929, ADR-0038 §8, §14): every Applicant the status filter and the search show,
// unpaged, as a print-styled table the browser prints. No app chrome; the toolbar carries
// `print:hidden`. Names are content, shown as stored.
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateOnly } from '@/lib/dates';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PhArrowLeft, PhPrinter } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface PrintRow {
    id: number;
    first_name: string;
    last_name: string;
    status: string;
    placement: string | null;
    applied_on: string;
    last_update: string;
    email: string | null;
    phone: string | null;
    mentor: string | null;
}

const props = defineProps<{
    status: string;
    search: string | null;
    applicants: PrintRow[];
}>();

const page = usePage<SharedData>();

const title = computed(() => trans('applicants.outputs.list_title', { status: trans(`applicants.status.${props.status}`) }));

// Back to the list as it was filtered.
const backHref = computed(() => {
    const query = new URLSearchParams();
    if (props.status !== 'all') {
        query.set('status', props.status);
    }
    if (props.search) {
        query.set('q', props.search);
    }
    const suffix = query.toString();
    return route('officer.members.list.applicants') + (suffix ? `?${suffix}` : '');
});

const statusLabel = (row: PrintRow) =>
    row.placement ? `${trans(`applicants.status.${row.status}`)} · ${row.placement}` : trans(`applicants.status.${row.status}`);

const print = () => window.print();
</script>

<template>
    <Head :title="title" />

    <div class="bg-background min-h-screen">
        <div class="flex flex-wrap gap-2 border-b p-4 print:hidden">
            <Button variant="outline" as-child>
                <Link :href="backHref">
                    <PhArrowLeft aria-hidden="true" />
                    {{ trans('applicants.outputs.back') }}
                </Link>
            </Button>
            <Button type="button" @click="print">
                <PhPrinter aria-hidden="true" />
                {{ trans('applicants.outputs.print') }}
            </Button>
        </div>

        <div class="text-rom-ink flex flex-col gap-4 p-6 text-sm print:p-0">
            <header>
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground">
                    <span v-if="search">{{ trans('applicants.outputs.search', { term: search }) }} · </span>
                    {{ transChoice('applicants.outputs.count', applicants.length, { count: String(applicants.length) }) }}
                </p>
            </header>

            <Table v-if="applicants.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('applicants.column.first_name') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.last_name') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.status') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.applied_on') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.last_update') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.email') }}</TableHead>
                        <TableHead>{{ trans('applicants.outputs.phone') }}</TableHead>
                        <TableHead>{{ trans('applicants.column.mentor') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="applicant in applicants" :key="applicant.id">
                        <TableCell>{{ applicant.first_name }}</TableCell>
                        <TableCell>{{ applicant.last_name }}</TableCell>
                        <TableCell class="whitespace-normal">{{ statusLabel(applicant) }}</TableCell>
                        <TableCell>{{ formatDateOnly(applicant.applied_on, page.props.locale) }}</TableCell>
                        <TableCell>{{ formatDateOnly(applicant.last_update, page.props.locale) }}</TableCell>
                        <TableCell class="break-all whitespace-normal">{{ applicant.email }}</TableCell>
                        <TableCell>{{ applicant.phone }}</TableCell>
                        <TableCell>{{ applicant.mentor ?? trans('applicants.unassigned') }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p v-else class="text-muted-foreground">{{ trans('applicants.list_empty') }}</p>
        </div>
    </div>
</template>
