<script setup lang="ts">
// Summary Committee Statistics (#413, PRD #406, ADR-0022 §8) — the first of the six DMV-wide
// reports and the single output the whole Hours feature exists to produce. Committees ×
// twelve months of scheduled hours, then org-wide rows for meeting hours, extra hours, and
// the grand total.
//
// The scheduled section lists only committees that run scheduling — a Group with no schedule
// can never carry a number there. Reports are officer-only (§4): the server gates this page to
// the DMV officers, Records, or the super-tier. All chrome is translated (ADR-0004); committee
// names render as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import OrgHoursReportNav from '@/components/OrgHoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DmvCommitteeSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<DmvCommitteeSummary>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.dmv.summary.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('hours.committee-summary') }]);

// The org-wide rows below the scheduled section, in display order.
const orgRows = computed(() => [
    { key: 'meetings', row: props.orgRows.meetings },
    { key: 'extra', row: props.orgRows.extra },
    { key: 'total', row: props.orgRows.total },
]);

const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(iso));

const csvHref = computed(() => route('hours.committee-summary.csv', { fy: props.fiscalYear }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.dmv.summary.lead') }}</p>
            </header>

            <OrgHoursReportNav active="summary" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.dmv.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('hours.committee-summary', { fy: year })"
                    :aria-current="year === fiscalYear ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="year === fiscalYear ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ trans('hours.dmv.fiscal_year', { year: String(year) }) }}
                </Link>
            </nav>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ trans('hours.dmv.fiscal_year', { year: String(fiscalYear) }) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.dmv.summary.column.committee') }}</th>
                                <th v-for="column in months" :key="column.year_month" class="py-2 pr-2 text-right font-semibold">
                                    {{ formatMonth(column.month) }}
                                </th>
                                <th class="py-2 pl-2 text-right font-semibold">{{ trans('hours.dmv.summary.column.ytd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Scheduled hours, one row per committee that runs scheduling. -->
                            <tr class="text-muted-foreground text-xs tracking-wide uppercase">
                                <td class="pt-3 pb-1 font-semibold" :colspan="months.length + 2">{{ trans('hours.dmv.summary.scheduled') }}</td>
                            </tr>
                            <tr v-for="row in scheduled" :key="row.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ row.name }}</td>
                                <td v-for="(hours, index) in row.months" :key="index" class="py-1.5 pr-2 text-right tabular-nums">{{ hours }}</td>
                                <td class="text-rom-ink py-1.5 pl-2 text-right font-semibold tabular-nums">{{ row.ytd }}</td>
                            </tr>
                            <tr v-if="!scheduled.length">
                                <td :colspan="months.length + 2" class="text-muted-foreground py-6 text-center">
                                    {{ trans('hours.dmv.summary.empty') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <!-- Org-wide rows: meeting hours, extra hours, and the grand total. -->
                            <tr
                                v-for="entry in orgRows"
                                :key="entry.key"
                                class="text-rom-ink font-semibold"
                                :class="{ 'border-border border-t-2': entry.key === 'meetings' }"
                            >
                                <td class="py-2 pr-4 text-xs tracking-wide uppercase">{{ trans(`hours.dmv.summary.${entry.key}`) }}</td>
                                <td v-for="(hours, index) in entry.row.months" :key="index" class="py-2 pr-2 text-right tabular-nums">{{ hours }}</td>
                                <td class="py-2 pl-2 text-right tabular-nums">{{ entry.row.ytd }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
