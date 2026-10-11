<script setup lang="ts">
// The closing rows Tour Summary and Tour Detail share (#800, ADR-0032 §12): the group-tour
// subtotal, the scheduled tours, the exhibition revenue, and the grand total, as a table footer.
// `labelColumns` is how many label cells the table has (one on the summary, two on the detail).
// The footer paints `bg-card`, the surface the pinned first column paints, not a muted band.
import { TableCell, TableFooter, TableRow } from '@/components/ui/table';
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
    <TableFooter class="bg-card border-primary font-normal">
        <TableRow class="font-bold">
            <TableCell :colspan="labelColumns">{{ trans('hours.tours.row.group_tours') }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ groupTours.tours }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ groupTours.visitors }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ money(groupTours.earned) }}</TableCell>
        </TableRow>
        <TableRow>
            <TableCell :colspan="labelColumns">{{ trans('hours.tours.row.scheduled') }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ scheduled.tours }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ scheduled.visitors }}</TableCell>
            <TableCell />
        </TableRow>
        <TableRow>
            <TableCell :colspan="labelColumns">{{ trans('hours.tours.row.exhibition') }}</TableCell>
            <TableCell />
            <TableCell />
            <TableCell class="text-right tabular-nums">{{ money(exhibitionRevenue) }}</TableCell>
        </TableRow>
        <TableRow class="border-primary border-t-2 font-bold">
            <TableCell :colspan="labelColumns" class="uppercase">{{ trans('hours.tours.row.grand_total') }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ grandTotal.tours }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ grandTotal.visitors }}</TableCell>
            <TableCell class="text-right tabular-nums">{{ money(grandTotal.earned) }}</TableCell>
        </TableRow>
    </TableFooter>
</template>
