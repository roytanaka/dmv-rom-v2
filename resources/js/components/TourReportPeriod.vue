<script setup lang="ts">
// The period picker shared by Tour Summary and Tour Detail (#800, ADR-0032 §12): a month select
// (`?month=YYYYMM`) and one link per fiscal year to date (`?fy=YYYY`). Each pick is a visit, so
// the period is addressable. Chrome only, so `print:hidden`; the card title names the period on
// paper.
import { NativeSelect, type NativeSelectValue } from '@/components/ui/native-select';
import { type SharedData, type TourReport } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { useId } from 'vue';

const props = defineProps<{
    routeName: string;
    groupSlug: string;
    period: TourReport['period'];
    months: TourReport['months'];
    fiscalYears: number[];
}>();

const page = usePage<SharedData>();
const selectId = useId();

const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));

const pick = (value: NativeSelectValue) => {
    if (value) {
        router.get(route(props.routeName, { group: props.groupSlug, month: String(value) }));
    }
};
</script>

<template>
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
            :href="route(routeName, { group: groupSlug, fy: year })"
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
