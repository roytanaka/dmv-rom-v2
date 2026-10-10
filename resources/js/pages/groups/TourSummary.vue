<script setup lang="ts">
// Tour Summary (#800, ADR-0032 §12) — a Group's group tours for a month or the fiscal year to
// date: tours (one per Sign-up), visitors and Earned per booking type, the group-tour subtotal,
// then a grand total with the scheduled tours and the exhibition revenue. The Statistician enters
// the month's exhibition revenue here.
//
// Officer-only: the server gates it to a Chair or Statistician of the Group or an ancestor, or the
// super-tier. Chrome is translated; type names render as-authored (ADR-0004). The table is the
// ROM listing Table (docs/conventions.md § Table), a data grid with its first column pinned.
import ExhibitionRevenueForm from '@/components/ExhibitionRevenueForm.vue';
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

const title = computed(() => trans('hours.tours.summary.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route('groups.hours.tour-summary', { group: props.group.slug }) },
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
            <TourReportHeader report="summary" :group="group" :period="period" :months="months" :fiscal-years="fiscalYears" />

            <ExhibitionRevenueForm
                v-if="exhibition.can_enter && period.year_month && period.month"
                :key="period.year_month"
                :group-slug="group.slug"
                :year-month="period.year_month"
                :month="period.month"
                :amount="exhibition.amount"
            />

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ periodName }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table pin-first-column>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ trans('hours.tours.column.type') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.tours') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.visitors') }}</TableHead>
                                <TableHead class="text-right">{{ trans('hours.tours.column.earned') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="type in types" :key="type.id">
                                <TableCell class="font-medium">{{ type.name }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ type.tours }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ type.visitors }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ money(type.earned) }}</TableCell>
                            </TableRow>
                            <TableEmpty v-if="!types.length" :colspan="4">{{ trans('hours.tours.empty') }}</TableEmpty>
                        </TableBody>
                        <TourReportTotals
                            :label-columns="1"
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
