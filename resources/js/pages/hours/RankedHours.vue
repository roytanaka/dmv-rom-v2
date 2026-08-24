<script setup lang="ts">
// Active Members Ranked Hours (#413, PRD #406, ADR-0022 §8) — every active and provisional
// member ordered by their total hours across the whole org this fiscal year, most first.
// Underneath, the members with no hours rows at all, so absence is visible rather than merely
// missing from the list.
//
// Reports are officer-only (§4): the org's per-member numbers are not open reading. All chrome
// is translated (ADR-0004); member names render as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import OrgHoursReportNav from '@/components/OrgHoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DmvRankedHours } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<DmvRankedHours>();

const title = computed(() => trans('hours.dmv.ranked.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('hours.ranked') }]);

const csvHref = computed(() => route('hours.ranked.csv', { fy: props.fiscalYear }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.dmv.ranked.lead') }}</p>
            </header>

            <OrgHoursReportNav active="ranked" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.dmv.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route('hours.ranked', { fy: year })"
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
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.dmv.ranked.column.member') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.dmv.ranked.column.scheduled') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.dmv.ranked.column.extra') }}</th>
                                <th class="py-2 text-right font-semibold">{{ trans('hours.dmv.ranked.column.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in ranked" :key="member.id" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ member.name }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ member.scheduled_hours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ member.extra_hours }}</td>
                                <td class="text-rom-ink py-1.5 text-right font-semibold tabular-nums">{{ member.total_hours }}</td>
                            </tr>
                            <tr v-if="!ranked.length">
                                <td colspan="4" class="text-muted-foreground py-6 text-center">{{ trans('hours.dmv.ranked.empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- The members with no hours rows at all — absence made visible, not merely missing. -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ trans('hours.dmv.ranked.no_hours') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="noHours.length" class="flex flex-col gap-1">
                        <li v-for="member in noHours" :key="member.id" class="text-rom-ink border-border/60 border-b py-1.5 font-medium">
                            {{ member.name }}
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground py-6 text-center">{{ trans('hours.dmv.ranked.none_missing') }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
