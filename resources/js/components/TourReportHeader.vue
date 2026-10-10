<script setup lang="ts">
// The top of Tour Summary and Tour Detail (#800, ADR-0032 §12), shared by both: the title and
// lead, the hours-report nav, Print and Export CSV, and the period picker. The picker is a month
// select (`?month=YYYYMM`) and one link per fiscal year to date (`?fy=YYYY`); each pick is a visit,
// so the period is addressable. The CSV link carries the same period. Chrome only below the title,
// so the picker is `print:hidden`; the report's card title names the period on paper.
import HoursReportActions from '@/components/HoursReportActions.vue';
import HoursReportNav from '@/components/HoursReportNav.vue';
import { NativeSelect, type NativeSelectValue } from '@/components/ui/native-select';
import { type SharedData, type TourReport } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, useId } from 'vue';

const props = defineProps<{
    report: 'summary' | 'detail';
    group: TourReport['group'];
    period: TourReport['period'];
    months: TourReport['months'];
    fiscalYears: number[];
}>();

const page = usePage<SharedData>();
const selectId = useId();

const routeName = computed(() => (props.report === 'summary' ? 'groups.hours.tour-summary' : 'groups.hours.tour-detail'));
const periodQuery = computed(() => (props.period.year_month ? { month: props.period.year_month } : { fy: props.period.fiscal_year }));
const csvHref = computed(() => route(`${routeName.value}.csv`, { group: props.group.slug, ...periodQuery.value }));

const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));

const pick = (value: NativeSelectValue) => {
    if (value) {
        router.get(route(routeName.value, { group: props.group.slug, month: String(value) }));
    }
};
</script>

<template>
    <header class="flex flex-col gap-1">
        <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ trans(`hours.tours.${report}.title`) }}</h1>
        <p class="text-muted-foreground text-sm">{{ trans(`hours.tours.${report}.lead`) }}</p>
    </header>

    <HoursReportNav :group-slug="group.slug" :has-bookings="group.has_bookings" :active="report === 'summary' ? 'tour_summary' : 'tour_detail'" />

    <HoursReportActions :csv-href="csvHref" />

    <div class="flex flex-wrap items-center gap-2 print:hidden">
        <label :for="selectId" class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{
            trans('hours.tours.pick_month')
        }}</label>
        <NativeSelect :id="selectId" class="w-auto" :model-value="period.year_month ?? ''" @update:model-value="pick">
            <option v-if="period.kind === 'year'" value="">—</option>
            <option v-for="column in months" :key="column.year_month" :value="column.year_month">{{ formatMonth(column.month) }}</option>
        </NativeSelect>
        <Link
            v-for="year in fiscalYears"
            :key="year"
            :href="route(routeName, { group: group.slug, fy: year })"
            :aria-current="period.kind === 'year' && period.fiscal_year === year ? 'page' : undefined"
            class="rounded-md px-3 py-1 text-sm font-medium"
            :class="
                period.kind === 'year' && period.fiscal_year === year ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'
            "
        >
            {{ trans('hours.tours.fiscal_to_date', { year: String(year) }) }}
        </Link>
    </div>
</template>
