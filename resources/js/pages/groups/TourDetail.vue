<script setup lang="ts">
// Tour Detail (#800, ADR-0032 §12) — Tour Summary's figures split by booking type and Tour, each
// type with its total, then the same group-tour subtotal and grand total. For a month or the
// fiscal year to date. Exhibition revenue is entered on Tour Summary.
//
// Officer-only: the server gates it to a Chair or Statistician of the Group or an ancestor, or the
// super-tier. Chrome is translated; type and Tour names render as-authored (ADR-0004).
import HoursReportActions from '@/components/HoursReportActions.vue';
import HoursReportNav from '@/components/HoursReportNav.vue';
import TourReportPeriod from '@/components/TourReportPeriod.vue';
import TourReportTotals from '@/components/TourReportTotals.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table } from '@/components/ui/table';
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

const periodQuery = computed(() => (props.period.year_month ? { month: props.period.year_month } : { fy: props.period.fiscal_year }));
const csvHref = computed(() => route('groups.hours.tour-detail.csv', { group: props.group.slug, ...periodQuery.value }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.tours.detail.lead') }}</p>
            </header>

            <HoursReportNav :group-slug="group.slug" :has-bookings="group.has_bookings" active="tour_detail" />

            <HoursReportActions :csv-href="csvHref" />

            <TourReportPeriod
                route-name="groups.hours.tour-detail"
                :group-slug="group.slug"
                :period="period"
                :months="months"
                :fiscal-years="fiscalYears"
            />

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ periodName }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table pin-first-column class="border-t-0">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.tours.column.type') }}</th>
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.tours.column.tour') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.tours.column.tours') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.tours.column.visitors') }}</th>
                                <th class="py-2 text-right font-semibold">{{ trans('hours.tours.column.earned') }}</th>
                            </tr>
                        </thead>
                        <tbody v-for="type in detail" :key="type.id">
                            <tr v-for="tour in type.tours" :key="tour.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ type.name }}</td>
                                <td class="py-1.5 pr-4">{{ tour.name }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ tour.tours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ tour.visitors }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ money(tour.earned) }}</td>
                            </tr>
                            <tr class="text-rom-ink border-border border-b font-semibold">
                                <td class="py-1.5 pr-4">{{ type.name }}</td>
                                <td class="py-1.5 pr-4">{{ trans('hours.tours.row.type_total') }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ type.total.tours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ type.total.visitors }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ money(type.total.earned) }}</td>
                            </tr>
                        </tbody>
                        <tbody v-if="!detail.length">
                            <tr>
                                <td colspan="5" class="text-muted-foreground py-8 text-center">{{ trans('hours.tours.empty') }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <TourReportTotals
                                :label-columns="2"
                                :group-tours="group_tours"
                                :scheduled="scheduled"
                                :exhibition-revenue="exhibition_revenue"
                                :grand-total="grand_total"
                            />
                        </tfoot>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
