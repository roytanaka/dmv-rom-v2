<script setup lang="ts">
// The closing rows Tour Summary and Tour Detail share (#800, ADR-0032 §12): the group-tour
// subtotal, the scheduled tours, the exhibition revenue, and the grand total. `labelColumns` is
// how many label cells the table has (one on the summary, two on the detail).
import { type SharedData, type TourReport } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    labelColumns: number;
    groupTours: TourReport['group_tours'];
    scheduled: TourReport['scheduled'];
    exhibitionRevenue: string;
    grandTotal: TourReport['grand_total'];
}>();

const page = usePage<SharedData>();
const money = (amount: string) => new Intl.NumberFormat(page.props.locale, { style: 'currency', currency: 'CAD' }).format(Number(amount));
</script>

<template>
    <tr class="text-rom-ink border-border border-t-2 font-semibold">
        <td :colspan="labelColumns" class="py-2 pr-4">{{ trans('hours.tours.row.group_tours') }}</td>
        <td class="py-2 pr-4 text-right tabular-nums">{{ groupTours.tours }}</td>
        <td class="py-2 pr-4 text-right tabular-nums">{{ groupTours.visitors }}</td>
        <td class="py-2 text-right tabular-nums">{{ money(groupTours.earned) }}</td>
    </tr>
    <tr class="border-border/60 border-b">
        <td :colspan="labelColumns" class="text-rom-ink py-1.5 pr-4">{{ trans('hours.tours.row.scheduled') }}</td>
        <td class="py-1.5 pr-4 text-right tabular-nums">{{ scheduled.tours }}</td>
        <td class="py-1.5 pr-4 text-right tabular-nums">{{ scheduled.visitors }}</td>
        <td class="py-1.5" />
    </tr>
    <tr class="border-border/60 border-b">
        <td :colspan="labelColumns" class="text-rom-ink py-1.5 pr-4">{{ trans('hours.tours.row.exhibition') }}</td>
        <td class="py-1.5 pr-4" />
        <td class="py-1.5 pr-4" />
        <td class="py-1.5 text-right tabular-nums">{{ money(exhibitionRevenue) }}</td>
    </tr>
    <tr class="text-rom-ink border-border border-t-2 font-semibold">
        <td :colspan="labelColumns" class="py-2 pr-4 tracking-wide uppercase">{{ trans('hours.tours.row.grand_total') }}</td>
        <td class="py-2 pr-4 text-right tabular-nums">{{ grandTotal.tours }}</td>
        <td class="py-2 pr-4 text-right tabular-nums">{{ grandTotal.visitors }}</td>
        <td class="py-2 text-right tabular-nums">{{ money(grandTotal.earned) }}</td>
    </tr>
</template>
