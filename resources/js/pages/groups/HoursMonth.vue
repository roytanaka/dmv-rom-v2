<script setup lang="ts">
// The month picker (#412, PRD #406, ADR-0022 §8) — one month's entries across the whole group,
// a row per Member. This is what a Statistician opens when the fiscal-year numbers look wrong
// and they want to know which month moved.
//
// Reports are not open reading (§4): the server gates this page to a Chair or Statistician of
// the group or any ancestor, or the super-tier. The month in view is the `?month=` query param,
// defaulting to the current month; the picker offers the months the group has records in plus
// the current one. All chrome is translated (ADR-0004); the group and member names render
// as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import HoursReportNav from '@/components/HoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type GroupHoursMonth, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<GroupHoursMonth>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.detail.month.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route('groups.hours.month', { group: props.group.slug }) },
]);

// The month name is a calendar month, not an instant: `month` is a first-of-month ISO date,
// so format it in UTC to name the month it is, never sliding a day into the one before.
const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));

const csvHref = computed(() => route('groups.hours.month.csv', { group: props.group.slug, month: props.month.year_month }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.detail.month.lead') }}</p>
            </header>

            <HoursReportNav :group-slug="group.slug" active="month" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Month picker — one link per month the group has records in, plus the current. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.detail.month.pick')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.detail.month.pick') }}</span>
                <Link
                    v-for="column in months"
                    :key="column.year_month"
                    :href="route('groups.hours.month', { group: group.slug, month: column.year_month })"
                    :aria-current="column.year_month === month.year_month ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="column.year_month === month.year_month ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ formatMonth(column.month) }}
                </Link>
            </nav>

            <Card>
                <!-- Names the month in view. The picker above does not print, so without
                     this the printout does not say which month it covers. -->
                <CardHeader>
                    <CardTitle class="text-base">{{ formatMonth(month.month) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.detail.month.column.member') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.detail.month.column.scheduled') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.detail.month.column.extra') }}</th>
                                <th class="py-2 text-right font-semibold">{{ trans('hours.detail.month.column.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ member.name }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ member.scheduled_hours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ member.extra_hours }}</td>
                                <td class="text-rom-ink py-1.5 text-right font-semibold tabular-nums">{{ member.total_hours }}</td>
                            </tr>
                            <!-- A month the group recorded nothing in — plain, never a broken page. -->
                            <tr v-if="!members.length">
                                <td colspan="4" class="text-muted-foreground py-8 text-center">
                                    {{ trans('hours.detail.month.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
