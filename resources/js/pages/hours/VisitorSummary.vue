<script setup lang="ts">
// Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the department's headline visitor
// number, Groups × twelve months over a fiscal year with a year-to-date column. Each Group's
// figure is its Sign-ups' two counts plus its extra interactions, rolled up through the whole
// sub-Group subtree; a Group whose figures are knowingly incomplete says so on its own row.
//
// This is the one DMV-wide report that is open to any signed-in Member (§6), so an ordinary
// Member reaches it from My Hours. Only a DMV officer sees the officer report nav — the rest of
// that family is officer-gated, so linking it to everyone would 403 an ordinary Member. All
// chrome is translated (ADR-0004); Group names render as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import OrgHoursReportNav from '@/components/OrgHoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DmvVisitorSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<DmvVisitorSummary>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.dmv.visitors.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('hours.visitor-summary') }]);

const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(iso));

// A Group's figures are marked incomplete when a booking audience it depends on is not counted yet.
const hasIncomplete = computed(() => props.groups.some((group) => group.incomplete));

// The CSV export carries the fiscal year in view, so it holds the same numbers as the screen.
const csvHref = computed(() => route('hours.visitor-summary.csv', { fy: props.fiscalYear }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.dmv.visitors.lead') }}</p>
            </header>

            <!-- Officers reach the rest of the report family here; ordinary Members arrive from My
                 Hours and never see the officer-gated nav (§6). -->
            <OrgHoursReportNav v-if="canViewOrgReports" active="visitors" />

            <!-- Print and CSV, the treatment every hours report carries (#452). Print hands the
                 page to the browser; the picker nav below is print:hidden so the dark selected
                 chip a browser drops can never read as the wrong year (the #429 lesson), and the
                 card title names the fiscal year the printed sheet covers. -->
            <HoursReportActions :csv-href="csvHref" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.dmv.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('hours.visitor-summary', { fy: year })"
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
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.dmv.visitors.column.group') }}</th>
                                <th v-for="column in months" :key="column.year_month" class="py-2 pr-2 text-right font-semibold">
                                    {{ formatMonth(column.month) }}
                                </th>
                                <th class="py-2 pl-2 text-right font-semibold">{{ trans('hours.dmv.visitors.column.ytd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in groups" :key="row.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">
                                    {{ row.name }}
                                    <!-- The incomplete marker rides the Group's own row, with its reason (§6). -->
                                    <abbr
                                        v-if="row.incomplete"
                                        :title="trans('hours.dmv.visitors.incomplete_note')"
                                        class="text-muted-foreground ml-1 cursor-help text-xs font-normal no-underline"
                                    >
                                        ({{ trans('hours.dmv.visitors.incomplete') }})
                                    </abbr>
                                </td>
                                <td v-for="cell in row.months" :key="cell.year_month" class="py-1.5 pr-2 text-right tabular-nums">
                                    {{ cell.interactions }}
                                </td>
                                <td class="text-rom-ink py-1.5 pl-2 text-right font-semibold tabular-nums">{{ row.ytd }}</td>
                            </tr>
                            <tr v-if="!groups.length">
                                <td :colspan="months.length + 2" class="text-muted-foreground py-6 text-center">
                                    {{ trans('hours.dmv.visitors.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- The reason the marker stands for, spelled out once below the table for readers
                         who cannot hover the abbreviation (touch, print, screen readers). -->
                    <p v-if="hasIncomplete" class="text-muted-foreground mt-4 text-xs">
                        ({{ trans('hours.dmv.visitors.incomplete') }}) — {{ trans('hours.dmv.visitors.incomplete_note') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
