<script setup lang="ts">
// The shared layout of the two Member × twelve-month summaries (#412, PRD #406, ADR-0022 §8):
// Member Extra Hours and Member Meeting Hours. They differ only in which records feed them
// (chosen server-side) and their title and lead, so the matrix, the fiscal-year picker, and
// the officer nav live here once. Each cell is a single hours figure; a Member with nothing
// this year reads zero across the row, never a gap.
//
// All chrome is translated (ADR-0004); the group and member names render as-authored.
import HoursReportNav from '@/components/HoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type GroupHoursSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<
    GroupHoursSummary & {
        active: 'extra' | 'meetings';
        titleKey: string;
        leadKey: string;
    }
>();

const page = usePage<SharedData>();

const title = computed(() => trans(props.titleKey));

const routeName = computed(() => (props.active === 'extra' ? 'groups.hours.extra' : 'groups.hours.meetings'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route(routeName.value, { group: props.group.slug }) },
]);

// The month name is a calendar month, not an instant: `month` is a first-of-month ISO date,
// so format it in UTC to name the month it is, never sliding a day into the one before.
const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(iso));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans(leadKey) }}</p>
            </header>

            <HoursReportNav :group-slug="group.slug" :active="active" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.detail.summary.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                    {{ trans('hours.detail.summary.pick_year') }}
                </span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route(routeName, { group: group.slug, fy: year })"
                    :aria-current="year === fiscalYear ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="year === fiscalYear ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ trans('hours.detail.summary.fiscal_year', { year: String(year) }) }}
                </Link>
            </nav>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ trans('hours.detail.summary.fiscal_year', { year: String(fiscalYear) }) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.detail.summary.column.member') }}</th>
                                <th v-for="column in months" :key="column.year_month" class="py-2 pr-2 text-right font-semibold">
                                    {{ formatMonth(column.month) }}
                                </th>
                                <th class="py-2 pl-2 text-right font-semibold">{{ trans('hours.detail.summary.column.ytd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ member.name }}</td>
                                <td v-for="cell in member.months" :key="cell.year_month" class="py-1.5 pr-2 text-right tabular-nums">
                                    {{ cell.hours }}
                                </td>
                                <td class="text-rom-ink py-1.5 pl-2 text-right font-semibold tabular-nums">{{ member.ytd }}</td>
                            </tr>
                            <!-- A fiscal year the group recorded nothing in — plain, never a broken page. -->
                            <tr v-if="!members.length">
                                <td :colspan="months.length + 2" class="text-muted-foreground py-8 text-center">
                                    {{ trans('hours.detail.summary.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
