<script setup lang="ts">
// The My Hours destination (#409, PRD #406, ADR-0022 §8) — a Member's own hours, gathered
// from every Group they have hours in, broken out by month across a fiscal year with a
// year-to-date total. This is the surface that answers the renewal question ("how many
// hours have I put in this year?"), and it lives outside any Group.
//
// It shows the viewer their own hours and nobody else's (§4). The fiscal year runs 1 April
// to 31 March and is named for the year it ends in; the picker navigates to a past year via
// the `?fy=` query param. Scheduled hours read zero until recalculation ships — honest, not
// broken. All chrome is translated (ADR-0004); Group names render as-authored.
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type MyHours, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhArrowRight, PhClock } from '@phosphor-icons/vue';
import { computed } from 'vue';

defineProps<MyHours>();

const page = usePage<SharedData>();

// `computed` so the title survives a full-page locale switch — messages load async, so a
// `trans()` snapshot taken at setup would capture the raw key.
const title = computed(() => trans('nav.personal.hours'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('hours') }]);

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
                <!-- The heading names the fiscal year, because the picker below is the only
                     other place it appears and the picker does not print. A filed printout
                     has to say which year it covers. -->
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }} — {{ trans('hours.mine.fiscal_year', { year: String(fiscalYear) }) }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.mine.lead') }}</p>
            </header>

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked.
                 `print:hidden` because the marked year reads as unmarked on paper: the
                 selected chip is a dark fill the browser drops when printing, which leaves
                 white text on white and makes an unselected year look like the chosen one. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.mine.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.mine.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('hours', { fy: year })"
                    :aria-current="year === fiscalYear ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="year === fiscalYear ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ trans('hours.mine.fiscal_year', { year: String(year) }) }}
                </Link>
            </nav>

            <!-- Every Member reaches Summary Visitor Interactions from here (#451, ADR-0023 §6):
                 the report is open to all, so it hangs off My Hours rather than only the officer
                 report nav an ordinary Member never sees. -->
            <Link
                :href="route('hours.visitor-summary')"
                class="text-rom-ink hover:bg-muted border-border inline-flex w-fit items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"
            >
                {{ trans('hours.mine.visitor_summary') }}
                <PhArrowRight class="h-4 w-4" />
            </Link>

            <!-- One card per Group the Member has hours in this fiscal year. -->
            <Card v-for="group in groups" :key="group.id">
                <CardHeader>
                    <CardTitle class="text-base">{{ group.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.mine.column.month') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.mine.column.scheduled') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.mine.column.extra') }}</th>
                                <th class="py-2 text-right font-semibold">{{ trans('hours.mine.column.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(cell, index) in group.months" :key="cell.year_month" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ formatMonth(months[index].month) }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ cell.scheduled_hours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ cell.extra_hours }}</td>
                                <td class="text-rom-ink py-1.5 text-right font-semibold tabular-nums">{{ cell.total_hours }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="text-rom-ink border-border border-t-2 font-semibold">
                                <td class="py-2 pr-4 text-xs tracking-wide uppercase">{{ trans('hours.mine.ytd') }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ group.ytd.scheduled_hours }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ group.ytd.extra_hours }}</td>
                                <td class="py-2 text-right tabular-nums">{{ group.ytd.total_hours }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </CardContent>
            </Card>

            <!-- A Member with no hours in this fiscal year — plain, so it is never mistaken
                 for a broken page. -->
            <Card v-if="!groups.length">
                <CardContent class="flex flex-col items-center gap-2 py-16 text-center">
                    <PhClock class="text-muted-foreground h-6 w-6" />
                    <p class="text-rom-ink font-medium">{{ trans('hours.mine.empty.heading') }}</p>
                    <p class="text-muted-foreground max-w-md text-sm">{{ trans('hours.mine.empty.body') }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
