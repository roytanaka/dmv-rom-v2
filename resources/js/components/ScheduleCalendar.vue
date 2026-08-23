<script setup lang="ts">
// Calendar view (#360, ADR-0021 §7) — the month grid the reader can choose instead of the
// Agenda. It reads the same day-grouped Shifts the Agenda does; a month is just those
// buckets laid out. A day with Shifts is a button into a day sheet that lists the very same
// ShiftCard the Agenda renders, so both views show identical facts and affordances — this
// component adds a layout, never a second set of Shift rules. Grid and month-set math live
// in the shared pure module (unit-tested); the component stays thin enough to read by eye.
import ForeignShiftBand from '@/components/ForeignShiftBand.vue';
import ShiftCard from '@/components/ShiftCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { bandsByGroup, buildMonthGrid, monthsInRange, type DayGroup, type MonthCell } from '@/scheduling/agenda';
import { type ForeignShiftItem, type SharedData, type ShiftAgendaItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhCaretLeft, PhCaretRight } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

// `foreign` are the other Groups' open Shifts per day (#361), laid out on the same grid; a
// grid cell has no room for a band, so the master switch (`foreignExpanded`) turns a compact
// "+N open" chip on per day, and the day sheet carries the attributed bands themselves.
const props = defineProps<{
    agenda: DayGroup<ShiftAgendaItem>[];
    foreign: DayGroup<ForeignShiftItem>[];
    foreignExpanded: boolean;
    startsOn: string;
    endsOn: string;
}>();

const emit = defineEmits<{
    take: [shift: ShiftAgendaItem];
    drop: [shift: ShiftAgendaItem];
    assign: [shift: ShiftAgendaItem];
    remove: [signUpId: number];
    edit: [shift: ShiftAgendaItem];
    delete: [shift: ShiftAgendaItem];
}>();

const page = usePage<SharedData>();

// The months the Schedule spans, in order — the Calendar pages through exactly these and no
// further, so a 3-day occasion has one page and a 30-day month one, a range crossing a
// boundary as many as it touches. The reader lands on the first.
const months = computed(() => monthsInRange(props.startsOn, props.endsOn));
const pageIndex = ref(0);
const current = computed(() => months.value[Math.min(pageIndex.value, months.value.length - 1)]);

const grid = computed(() => buildMonthGrid(props.agenda, current.value.year, current.value.month));

// Foreign open Shifts keyed by day, for the per-cell chip and the day sheet's bands.
const foreignByDate = computed(() => new Map(props.foreign.map((group) => [group.date, group.shifts])));
const foreignOn = (date: string) => foreignByDate.value.get(date) ?? [];

const step = (delta: number) => {
    pageIndex.value = Math.min(Math.max(pageIndex.value + delta, 0), months.value.length - 1);
};

const monthTitle = computed(() =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric' }).format(new Date(current.value.year, current.value.month - 1, 1)),
);

// Weekday column headings, in the active locale, read off a known Sunday (2026-08-02) so
// they match the Sunday-first grid the pure module builds.
const weekdayLabels = computed(() => {
    const format = new Intl.DateTimeFormat(page.props.locale, { weekday: 'short' });
    return Array.from({ length: 7 }, (_, i) => format.format(new Date(2026, 7, 2 + i)));
});

// The opened day's cell — its Shifts are shown in the day sheet. A blank or Shift-free cell
// never opens; there is nothing to show.
const openDay = ref<MonthCell<ShiftAgendaItem> | null>(null);

const dayNumber = (date: string) => Number(date.slice(8, 10));

const openSheet = (cell: MonthCell<ShiftAgendaItem>) => {
    if (cell.date && (cell.shifts.length || (props.foreignExpanded && foreignOn(cell.date).length))) openDay.value = cell;
};

const sheetOpen = computed({
    get: () => openDay.value !== null,
    set: (open: boolean) => {
        if (!open) openDay.value = null;
    },
});

// The opened day's foreign Shifts, banded and attributed by owning Group — the same bands
// the Agenda shows, just surfaced in the day sheet where a grid cell had no room.
const openBands = computed(() => (openDay.value?.date ? bandsByGroup(foreignOn(openDay.value.date)) : []));

const formatDay = (date: string) =>
    new Intl.DateTimeFormat(page.props.locale, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date(`${date}T00:00:00`));
</script>

<template>
    <section class="flex flex-col gap-4" :aria-label="trans('group.scheduling_panel.calendar.aria_label')">
        <!-- Month header with paging, bounded to the Schedule's own months. -->
        <div class="flex items-center justify-between gap-2">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="gap-1.5"
                :disabled="pageIndex === 0"
                :aria-label="trans('group.scheduling_panel.calendar.previous')"
                @click="step(-1)"
            >
                <PhCaretLeft class="size-4" />
            </Button>
            <h3 class="text-rom-ink text-base font-medium capitalize">{{ monthTitle }}</h3>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="gap-1.5"
                :disabled="pageIndex >= months.length - 1"
                :aria-label="trans('group.scheduling_panel.calendar.next')"
                @click="step(1)"
            >
                <PhCaretRight class="size-4" />
            </Button>
        </div>

        <!-- The month grid: seven weekday columns, one row per calendar week. A day with
             Shifts is a button into its sheet; blanks and empty days are inert. -->
        <div class="grid grid-cols-7 gap-1">
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="text-muted-foreground pb-1 text-center text-xs font-medium tracking-wide uppercase"
            >
                {{ label }}
            </div>

            <template v-for="(week, w) in grid.weeks" :key="w">
                <template v-for="(cell, d) in week" :key="`${w}-${d}`">
                    <div v-if="!cell.date" class="min-h-16 rounded-md"></div>
                    <button
                        v-else-if="cell.shifts.length || (foreignExpanded && foreignOn(cell.date).length)"
                        type="button"
                        class="hover:border-rom-ink/40 focus-visible:ring-ring flex min-h-16 flex-col gap-1 rounded-md border p-1.5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        @click="openSheet(cell)"
                    >
                        <span class="text-rom-ink text-sm font-medium tabular-nums">{{ dayNumber(cell.date) }}</span>
                        <span
                            v-if="cell.shifts.length"
                            class="bg-secondary text-secondary-foreground mt-auto self-start rounded-full px-1.5 py-0.5 text-xs"
                        >
                            {{ transChoice('group.scheduling_panel.calendar.shift_count', cell.shifts.length) }}
                        </span>
                        <!-- Foreign open Shifts, chips off until the master switch turns them on
                             (a grid cell has no room for a band). -->
                        <span
                            v-if="foreignExpanded && foreignOn(cell.date).length"
                            class="border-rom-ink/30 text-muted-foreground mt-auto self-start rounded-full border border-dashed px-1.5 py-0.5 text-xs"
                        >
                            {{ trans('group.scheduling_panel.foreign.chip', { count: String(foreignOn(cell.date).length) }) }}
                        </span>
                    </button>
                    <div v-else class="min-h-16 rounded-md border border-transparent p-1.5">
                        <span class="text-muted-foreground text-sm tabular-nums">{{ dayNumber(cell.date) }}</span>
                    </div>
                </template>
            </template>
        </div>

        <!-- Day sheet — the clicked day's Shifts, each the same card the Agenda renders. -->
        <Dialog v-model:open="sheetOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ openDay?.date ? formatDay(openDay.date) : '' }}</DialogTitle>
                </DialogHeader>
                <div v-if="openDay" class="flex flex-col gap-3">
                    <ShiftCard
                        v-for="shift in openDay.shifts"
                        :key="shift.id"
                        :shift="shift"
                        @take="emit('take', $event)"
                        @drop="emit('drop', $event)"
                        @assign="emit('assign', $event)"
                        @remove="emit('remove', $event)"
                        @edit="emit('edit', $event)"
                        @delete="emit('delete', $event)"
                    />
                    <!-- Foreign open Shifts on the day, banded and attributed — the same band the
                         Agenda shows, surfaced here where the grid cell had no room. -->
                    <ForeignShiftBand
                        v-for="band in openBands"
                        :key="band.group"
                        :band="band"
                        :expanded="true"
                        @take="emit('take', $event)"
                        @drop="emit('drop', $event)"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>
