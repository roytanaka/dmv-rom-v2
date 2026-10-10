<script setup lang="ts">
// Tour Detail (#800, ADR-0032 §12) — Tour Summary's figures split by booking type and Tour, each
// type with its total, then the same group-tour subtotal and grand total. For a month or the
// fiscal year to date. Exhibition revenue is entered on Tour Summary.
//
// Officer-only: the server gates it to a Chair or Statistician of the Group or an ancestor, or the
// super-tier. Chrome is translated; type and Tour names render as-authored (ADR-0004). The table
// is the ROM listing Table (docs/conventions.md § Table), a data grid with its first column pinned.
import TourReportHeader from '@/components/TourReportHeader.vue';
import TourReportTotals from '@/components/TourReportTotals.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData, type TourReport } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<TourReport>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.tours.detail.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route('groups.hours.tour-detail', { group: props.group.slug }) },
]);

const money = (amount: string) => new Intl.NumberFormat(page.props.locale, { style: 'currency', currency: 'CAD' }).format(Number(amount));

// Names the period on paper, where the picker does not print.
const periodName = computed(() =>
    props.period.month
        ? new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(props.period.month))
        : trans('hours.tours.fiscal_to_date', { year: String(props.period.fiscal_year) }),
);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <TourReportHeader report="detail" :group="group" :period="period" :months="months" :fiscal-years="fiscalYears" />

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ periodName }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table pin-first-column>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ trans('hours.tours.column.type') }}</TableHead>
                                <TableHead>{{ trans('hours.tours.column.tour') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.tours') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.visitors') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.earned') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="type in detail" :key="type.id">
                                <TableRow v-for="tour in type.tours" :key="tour.id">
                                    <TableCell class="font-medium">{{ type.name }}</TableCell>
                                    <TableCell>{{ tour.name }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ tour.tours }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ tour.visitors }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ money(tour.earned) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-primary border-b font-bold">
                                    <TableCell>{{ type.name }}</TableCell>
                                    <TableCell>{{ trans('hours.tours.row.type_total') }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ type.total.tours }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ type.total.visitors }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ money(type.total.earned) }}</TableCell>
                                </TableRow>
                            </template>
                            <TableEmpty v-if="!detail.length" :colspan="5">{{ trans('hours.tours.empty') }}</TableEmpty>
                        </TableBody>
                        <TourReportTotals
                            :label-columns="2"
                            :group-tours="group_tours"
                            :scheduled="scheduled"
                            :exhibition-revenue="exhibition_revenue"
                            :grand-total="grand_total"
                        />
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
