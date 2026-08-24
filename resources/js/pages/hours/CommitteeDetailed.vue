<script setup lang="ts">
// Detailed Committee Statistics (#413, PRD #406, ADR-0022 §8) — the same twelve months as the
// summary, but each committee broken into shifts, meetings, and extra hours, so a reader can
// see where a committee's hours came from. Each committee row rolls up its whole subtree.
//
// The DMV total row includes every sub-group — the root's subtree is the whole department — so
// the org total is complete. Reports are officer-only (§4). All chrome is translated (ADR-0004);
// committee names render as-authored.
import OrgHoursReportNav from '@/components/OrgHoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DmvCommitteeDetailed, type DmvCommitteeRow, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<DmvCommitteeDetailed>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.dmv.detailed.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('hours.committee-detailed') }]);

// The three parts each committee is broken into, in display order. The cell key names the
// field on a month cell and the ytd; the label key names its translated row heading.
const kinds = [
    { field: 'shifts', label: 'shifts' },
    { field: 'meetings', label: 'meetings' },
    { field: 'extra', label: 'extra' },
] as const;

// Does any committee (or the DMV total) carry a number this year? Drives the empty state.
const isEmpty = computed(() => props.org.ytd.total === 0 && props.committees.every((c) => c.ytd.total === 0));

const cell = (row: DmvCommitteeRow, monthIndex: number, field: (typeof kinds)[number]['field']) => row.months[monthIndex][field];

const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(iso));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.dmv.detailed.lead') }}</p>
            </header>

            <OrgHoursReportNav active="detailed" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.dmv.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('hours.committee-detailed', { fy: year })"
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
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.dmv.detailed.column.committee') }}</th>
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.dmv.detailed.column.kind') }}</th>
                                <th v-for="column in months" :key="column.year_month" class="py-2 pr-2 text-right font-semibold">
                                    {{ formatMonth(column.month) }}
                                </th>
                                <th class="py-2 pl-2 text-right font-semibold">{{ trans('hours.dmv.detailed.column.ytd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- One three-row block per committee: shifts, meetings, extra. -->
                            <template v-for="committee in committees" :key="committee.id">
                                <tr
                                    v-for="(kind, kindIndex) in kinds"
                                    :key="kind.field"
                                    :class="{ 'border-border/60 border-b': kindIndex === kinds.length - 1 }"
                                >
                                    <td v-if="kindIndex === 0" :rowspan="kinds.length" class="text-rom-ink py-1.5 pr-4 align-top font-medium">
                                        {{ committee.name }}
                                    </td>
                                    <td class="text-muted-foreground py-1 pr-4">{{ trans(`hours.dmv.detailed.kind.${kind.label}`) }}</td>
                                    <td v-for="(column, monthIndex) in months" :key="column.year_month" class="py-1 pr-2 text-right tabular-nums">
                                        {{ cell(committee, monthIndex, kind.field) }}
                                    </td>
                                    <td class="text-rom-ink py-1 pl-2 text-right font-semibold tabular-nums">{{ committee.ytd[kind.field] }}</td>
                                </tr>
                            </template>
                            <tr v-if="isEmpty">
                                <td :colspan="months.length + 3" class="text-muted-foreground py-6 text-center">
                                    {{ trans('hours.dmv.detailed.empty') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <!-- The DMV total — the root's whole subtree, so the org total is complete. -->
                            <tr
                                v-for="(kind, kindIndex) in kinds"
                                :key="kind.field"
                                class="text-rom-ink font-semibold"
                                :class="{ 'border-border border-t-2': kindIndex === 0 }"
                            >
                                <td v-if="kindIndex === 0" :rowspan="kinds.length" class="py-2 pr-4 align-top text-xs tracking-wide uppercase">
                                    {{ trans('hours.dmv.detailed.total') }}
                                </td>
                                <td class="text-muted-foreground py-1 pr-4 font-normal">{{ trans(`hours.dmv.detailed.kind.${kind.label}`) }}</td>
                                <td v-for="(column, monthIndex) in months" :key="column.year_month" class="py-1 pr-2 text-right tabular-nums">
                                    {{ cell(org, monthIndex, kind.field) }}
                                </td>
                                <td class="py-1 pl-2 text-right tabular-nums">{{ org.ytd[kind.field] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
