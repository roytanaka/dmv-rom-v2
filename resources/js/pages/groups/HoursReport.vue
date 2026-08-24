<script setup lang="ts">
// The Group fiscal-year hours report (#411, PRD #406, ADR-0022 §5) — a Member × twelve-month
// matrix with a year-to-date column, and two rollups side by side: the group's own hours and
// its hours including every descendant, however deep the tree goes.
//
// Reports are not open reading (§4): the server gates this page to a Chair or Statistician of
// the group or any ancestor, or the super-tier — an ordinary member never reaches it. All
// chrome is translated (ADR-0004); the group and member names render as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import HoursReportNav from '@/components/HoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type GroupHoursReport, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<GroupHoursReport>();

const page = usePage<SharedData>();

// `computed` so the title survives a full-page locale switch — messages load async, so a
// `trans()` snapshot taken at setup would capture the raw key.
const title = computed(() => trans('hours.report.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route('groups.hours.report', { group: props.group.slug }) },
]);

// The month name is a calendar month, not an instant: `month` is a first-of-month ISO date,
// so format it in UTC to name the month it is, never sliding a day into the one before.
const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(iso));

const csvHref = computed(() => route('groups.hours.report.csv', { group: props.group.slug, fy: props.fiscalYear }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.report.lead') }}</p>
            </header>

            <HoursReportNav :group-slug="group.slug" active="report" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.report.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.report.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('groups.hours.report', { group: group.slug, fy: year })"
                    :aria-current="year === fiscalYear ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="year === fiscalYear ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ trans('hours.report.fiscal_year', { year: String(year) }) }}
                </Link>
            </nav>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ trans('hours.report.fiscal_year', { year: String(fiscalYear) }) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.report.column.member') }}</th>
                                <th v-for="column in months" :key="column.year_month" class="py-2 pr-2 text-right font-semibold">
                                    {{ formatMonth(column.month) }}
                                </th>
                                <th class="py-2 pl-2 text-right font-semibold">{{ trans('hours.report.column.ytd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ member.name }}</td>
                                <td v-for="cell in member.months" :key="cell.year_month" class="py-1.5 pr-2 text-right tabular-nums">
                                    {{ cell.total_hours }}
                                </td>
                                <td class="text-rom-ink py-1.5 pl-2 text-right font-semibold tabular-nums">{{ member.ytd.total_hours }}</td>
                            </tr>
                            <!-- A fiscal year the group recorded nothing in — plain, never a broken page. -->
                            <tr v-if="!members.length">
                                <td :colspan="months.length + 2" class="text-muted-foreground py-8 text-center">
                                    {{ trans('hours.report.empty') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <!-- The two rollups side by side: the group's own hours and its subtree hours. -->
                            <tr class="text-rom-ink border-border border-t-2 font-semibold">
                                <td class="py-2 pr-4 text-xs tracking-wide uppercase">{{ trans('hours.report.own') }}</td>
                                <td v-for="(hours, index) in totals.own.months" :key="index" class="py-2 pr-2 text-right tabular-nums">
                                    {{ hours }}
                                </td>
                                <td class="py-2 pl-2 text-right tabular-nums">{{ totals.own.ytd }}</td>
                            </tr>
                            <tr class="text-rom-ink font-semibold">
                                <td class="py-2 pr-4 text-xs tracking-wide uppercase">{{ trans('hours.report.subtree') }}</td>
                                <td v-for="(hours, index) in totals.subtree.months" :key="index" class="py-2 pr-2 text-right tabular-nums">
                                    {{ hours }}
                                </td>
                                <td class="py-2 pl-2 text-right tabular-nums">{{ totals.subtree.ytd }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
