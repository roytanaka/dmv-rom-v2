<script setup lang="ts">
// PROTOTYPE VARIANT C — "Matrix". See #330.
//
// Days across the top, one row per time band *or* per activity — swappable. A cell is
// the intersection, drawn as one small square per slot: solid = taken, outlined =
// open, slate = yours. Clicking an open square takes it.
//
// This is legacy's Activities-Across and Activities-Down as a single component with an
// axis swap, which is the direct answer to "does `DisplayStyle` need to survive?".
// It is also the only one of the three that draws Adrian's "three-dimensional
// intersect" — time × activity × people — without collapsing an axis.
//
// The bet: for anything with structure (the same bands every day, the same positions
// every day), the pattern IS the information, and a matrix shows the pattern where a
// list hides it. The cost is 30 columns on a month, and horizontal scroll on a phone.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PhPlus } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';
import { canSeeNames, canTake, dayNumber, isMine, rangeLabel, take, timeRange, totals, weekdayNarrow } from './helpers';
import type { Schedule, Shift, Viewer } from './types';

const props = defineProps<{ schedule: Schedule; viewer: Viewer; groupName: string; showForeign: boolean }>();

type Axis = 'time' | 'kind';
const axis = ref<Axis>('time');

const visible = computed(() => props.schedule.shifts.filter((s) => (s.foreignGroup === undefined ? true : props.showForeign)));

// Every day the Schedule covers, including the ones nothing happens on — the gaps are
// half of what a matrix is for.
const days = computed(() => {
    const out: string[] = [];
    const cursor = new Date(`${props.schedule.startsOn}T00:00`);
    const end = new Date(`${props.schedule.endsOn}T00:00`);
    while (cursor <= end) {
        out.push(`${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`);
        cursor.setDate(cursor.getDate() + 1);
    }
    return out;
});

const rowKey = (shift: Shift) => (axis.value === 'time' ? timeRange(shift) : (shift.kind ?? '—'));

interface Band {
    label: string;
    foreignGroup?: string;
}

// Own rows first; a foreign Group's Shifts get their own band underneath, never mixed
// into the viewer's own rows.
const bands = computed<Band[]>(() => {
    const own = [...new Set(visible.value.filter((s) => !s.foreignGroup).map(rowKey))].sort();
    const foreign = [...new Set(visible.value.filter((s) => s.foreignGroup).map((s) => `${s.foreignGroup}|${rowKey(s)}`))].sort();
    return [...own.map((label) => ({ label })), ...foreign.map((entry) => ({ label: entry.split('|')[1], foreignGroup: entry.split('|')[0] }))];
});

const cell = (band: Band, date: string) =>
    visible.value.filter((s) => s.startsAt.slice(0, 10) === date && rowKey(s) === band.label && (s.foreignGroup ?? undefined) === band.foreignGroup);

const counts = computed(() => totals(props.schedule.shifts.filter((s) => s.foreignGroup === undefined)));

// Column width shrinks once a Schedule gets long — a 3-day event gets room to breathe,
// a 31-day month gets a scrollbar either way.
const wide = computed(() => days.value.length <= 10);

const seatTitle = (shift: Shift, index: number) => {
    const signup = shift.signups[index];
    if (!signup) return `${shift.kind ?? 'Shift'} ${timeRange(shift)} — open`;
    return canSeeNames(props.viewer)
        ? `${signup.person.name}${signup.assigned ? ' (assigned)' : ''}`
        : `${shift.kind ?? 'Shift'} ${timeRange(shift)} — taken`;
};

const seatTone = (shift: Shift, index: number) => {
    const signup = shift.signups[index];
    if (!signup) return shift.foreignGroup ? 'border-rom-slate/50 border-dashed bg-white' : 'border-success/60 bg-white hover:bg-success-bg';
    return signup.person.id === 1 ? 'bg-rom-slate border-rom-slate' : 'bg-rom-ink/70 border-rom-ink/70';
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 class="text-rom-ink text-2xl font-semibold">{{ schedule.name }}</h2>
                <Badge v-if="schedule.state === 'draft'" variant="warning">Draft</Badge>
                <span class="text-muted-foreground text-sm">{{ rangeLabel(schedule) }}</span>
            </div>
            <p v-if="schedule.description" class="text-rom-ink max-w-2xl text-base">{{ schedule.description }}</p>
            <p class="text-muted-foreground text-sm">
                {{ counts.shifts }} shifts · {{ counts.open }} slots open<template v-if="viewer.role !== 'nonmember'">
                    · you're on {{ counts.mine }}</template
                >
            </p>
            <p v-if="viewer.role === 'nonmember'" class="text-muted-foreground text-sm italic">
                You're not a member of {{ groupName }} — this schedule is read-only for you.
            </p>
        </div>

        <div v-if="viewer.role === 'scheduler'" class="border-rom-slate/30 bg-rom-slate-50 flex flex-wrap items-center gap-2 border p-3">
            <Button size="sm" class="gap-1.5"><PhPlus class="size-4" /> Add shift</Button>
            <Button size="sm" variant="secondary">Copy from another schedule</Button>
            <Button size="sm" variant="ghost">Edit schedule</Button>
            <span class="text-muted-foreground ml-auto text-xs">Click a square to place a volunteer</span>
        </div>

        <!-- The axis swap. Legacy shipped this as three stored DisplayStyle values on
             the event; here it is a viewer-side toggle that nothing persists. -->
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-muted-foreground text-sm">Rows:</span>
            <div class="border-input flex border">
                <button
                    v-for="option in ['time', 'kind'] as Axis[]"
                    :key="option"
                    type="button"
                    class="px-3 py-1.5 text-sm transition-colors"
                    :class="axis === option ? 'bg-rom-ink text-white' : 'text-muted-foreground hover:text-rom-ink'"
                    @click="axis = option"
                >
                    {{ option === 'time' ? 'Time of day' : 'Activity' }}
                </button>
            </div>
            <span class="text-muted-foreground ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span class="flex items-center gap-1.5"><span class="border-success/60 size-3 border bg-white"></span> open</span>
                <span class="flex items-center gap-1.5"><span class="bg-rom-ink/70 size-3"></span> taken</span>
                <span class="flex items-center gap-1.5"><span class="bg-rom-slate size-3"></span> yours</span>
            </span>
        </div>

        <div v-if="!schedule.shifts.length" class="border-input flex flex-col items-center gap-3 border border-dashed px-6 py-16 text-center">
            <p class="text-rom-ink text-lg font-medium">Nothing to lay out yet</p>
            <p class="text-muted-foreground max-w-md text-sm">
                A grid needs rows. Add the shifts for one day and the rest of {{ schedule.name }} can be filled in against them.
            </p>
            <div v-if="viewer.role === 'scheduler'" class="mt-2 flex flex-wrap justify-center gap-2">
                <Button class="gap-1.5"><PhPlus class="size-4" /> Add the first shift</Button>
                <Button variant="secondary">Copy from November 2026</Button>
            </div>
        </div>

        <!-- The matrix. Sticky first column; the rest scrolls sideways. -->
        <div v-else class="border-input overflow-x-auto border">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-muted/60">
                        <th class="bg-muted/60 border-input sticky left-0 z-10 border-r px-3 py-2 text-left text-xs font-semibold">
                            {{ axis === 'time' ? 'Time' : 'Activity' }}
                        </th>
                        <th
                            v-for="date in days"
                            :key="date"
                            class="border-input text-muted-foreground border-l px-1 py-2 text-center text-xs font-medium"
                            :class="wide ? 'min-w-32' : 'min-w-14'"
                        >
                            <span class="block">{{ weekdayNarrow(date) }}</span>
                            <span class="text-rom-ink block font-semibold">{{ dayNumber(date) }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="band in bands" :key="`${band.foreignGroup ?? ''}${band.label}`" class="border-input border-t">
                        <th
                            class="bg-background border-input sticky left-0 z-10 max-w-40 border-r px-3 py-2 text-left align-top text-xs font-medium whitespace-nowrap"
                            :class="band.foreignGroup ? 'text-rom-slate' : 'text-rom-ink'"
                        >
                            <span class="block">{{ band.label }}</span>
                            <span v-if="band.foreignGroup" class="text-muted-foreground block text-[0.65rem] font-normal">{{
                                band.foreignGroup
                            }}</span>
                        </th>
                        <td v-for="date in days" :key="date" class="border-input border-l px-1 py-2 align-top">
                            <div class="flex flex-col gap-2.5">
                                <div v-for="shift in cell(band, date)" :key="shift.id" class="flex flex-col items-center gap-1">
                                    <!-- The cell's second axis: whichever of kind/time the
                                         row headers are NOT already carrying. -->
                                    <span v-if="wide" class="text-muted-foreground text-center text-[0.65rem] leading-tight">
                                        {{ axis === 'time' ? shift.kind : timeRange(shift) }}
                                    </span>
                                    <!-- One square per slot. #326 made a Shift a slot with a
                                         capacity, so this is a rendering of a number — not
                                         legacy's N stored rows. -->
                                    <div class="flex flex-wrap justify-center gap-0.5">
                                        <button
                                            v-for="i in shift.capacity"
                                            :key="i"
                                            type="button"
                                            class="size-4 border transition-colors"
                                            :class="seatTone(shift, i - 1)"
                                            :title="seatTitle(shift, i - 1)"
                                            :disabled="!!shift.signups[i - 1] || !canTake(shift, viewer)"
                                            @click="take(shift)"
                                        ></button>
                                    </div>
                                    <span v-if="isMine(shift)" class="text-rom-slate text-[0.6rem] leading-none font-semibold">you</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-muted-foreground text-xs">
            {{ days.length }} columns. On a phone this scrolls sideways with the row labels pinned — the thing to judge is whether that is workable or
            merely possible.
        </p>
    </div>
</template>
