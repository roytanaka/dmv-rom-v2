<script setup lang="ts">
// PROTOTYPE VARIANT B — "Calendar". See #330.
//
// The month grid is the surface; a day is a cell of chips, and opening a day opens a
// sheet with the detail. Descended from what Visitor Guides already does in legacy —
// a grid of small per-slot buttons where an empty button *is* the sign-up affordance.
//
// The bet: the question a volunteer actually brings to a Schedule is spatial ("which
// Saturdays am I on, and where are the holes?"), and a month answers that at a glance
// in a way a list never can.
//
// It is drawn deliberately unflattering on the three-day event fixture: the grid
// renders the weeks the range spans and most of it is empty. That is the finding, not
// a bug — it is the evidence for whether one layout can serve both shapes.
//
// On small screens the cells drop their chip labels and keep only fill dots: the
// month stays a month, and the detail moves entirely into the sheet.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PhPlus } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';
import {
    calendarWeeks,
    canAuthor,
    canSeeNames,
    canTake,
    dayLong,
    dayNumber,
    drop,
    inRange,
    isFull,
    isMine,
    rangeLabel,
    remaining,
    removeSignup,
    take,
    timeRange,
    totals,
    weekdayNarrow,
} from './helpers';
import type { Schedule, Shift, Viewer } from './types';

const props = defineProps<{ schedule: Schedule; viewer: Viewer; groupName: string; showForeign: boolean }>();

const openDay = ref<string | null>(null);
const sheetOpen = computed({ get: () => openDay.value !== null, set: (v: boolean) => (openDay.value = v ? openDay.value : null) });

const visible = computed(() => props.schedule.shifts.filter((s) => (s.foreignGroup === undefined ? true : props.showForeign)));

const weeks = computed(() => calendarWeeks(props.schedule.startsOn, props.schedule.endsOn));

const shiftsOn = (date: string) => visible.value.filter((s) => s.startsAt.slice(0, 10) === date).sort((a, b) => a.startsAt.localeCompare(b.startsAt));

const daySheetShifts = computed(() => (openDay.value ? shiftsOn(openDay.value) : []));

const counts = computed(() => totals(props.schedule.shifts.filter((s) => s.foreignGroup === undefined)));

// A chip's tone. Mine is the loud one; an empty chip is outlined so "there is a hole
// here" reads as an invitation rather than a warning.
const chipTone = (shift: Shift) =>
    isMine(shift)
        ? 'bg-rom-slate text-white border-rom-slate'
        : shift.foreignGroup
          ? 'border-rom-slate/50 border-dashed text-rom-slate bg-rom-slate-50'
          : isFull(shift)
            ? 'bg-muted text-muted-foreground border-transparent'
            : 'bg-background text-rom-ink border-success/60';
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
            <span class="text-muted-foreground ml-auto text-xs">Click any day to add shifts to it</span>
        </div>

        <!-- Legend. A grid needs one; a list does not — worth noticing. -->
        <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
            <span class="flex items-center gap-1.5"><span class="border-success/60 size-3 border bg-white"></span> open</span>
            <span class="flex items-center gap-1.5"><span class="bg-muted size-3"></span> full</span>
            <span class="flex items-center gap-1.5"><span class="bg-rom-slate size-3"></span> yours</span>
            <span v-if="showForeign" class="flex items-center gap-1.5"
                ><span class="border-rom-slate/50 bg-rom-slate-50 size-3 border border-dashed"></span> another Group</span
            >
        </div>

        <!-- The grid. Cells keep a fixed minimum height so a bare week reads as bare. -->
        <div class="border-input overflow-hidden border">
            <div class="bg-muted/60 grid grid-cols-7">
                <div v-for="d in weeks[0]" :key="d" class="text-muted-foreground p-1.5 text-center text-xs font-semibold sm:p-2">
                    <span class="lg:hidden">{{ weekdayNarrow(d).slice(0, 1) }}</span>
                    <span class="hidden lg:inline">{{ weekdayNarrow(d) }}</span>
                </div>
            </div>

            <div v-for="(week, wi) in weeks" :key="wi" class="border-input grid grid-cols-7 border-t">
                <div
                    v-for="date in week"
                    :key="date"
                    class="border-input min-h-20 border-r p-1 last:border-r-0 sm:min-h-24 lg:min-h-28"
                    :class="inRange(date, schedule) ? 'bg-background' : 'bg-muted/30'"
                >
                    <button type="button" class="flex w-full flex-col gap-1 text-left" :disabled="!inRange(date, schedule)" @click="openDay = date">
                        <span class="text-xs font-semibold" :class="inRange(date, schedule) ? 'text-rom-ink' : 'text-muted-foreground/50'">
                            {{ dayNumber(date) }}
                        </span>

                        <!-- lg+: labelled chips. Below lg: fill dots only. -->
                        <span class="hidden flex-col gap-0.5 lg:flex">
                            <span
                                v-for="shift in shiftsOn(date)"
                                :key="shift.id"
                                class="flex items-center justify-between gap-1 border px-1 py-0.5 text-[0.65rem] leading-tight"
                                :class="chipTone(shift)"
                            >
                                <span class="truncate">{{ shift.kind ?? 'Shift' }}</span>
                                <span class="shrink-0 font-mono">{{ shift.signups.length }}/{{ shift.capacity }}</span>
                            </span>
                        </span>

                        <span class="flex flex-wrap gap-0.5 lg:hidden">
                            <span v-for="shift in shiftsOn(date)" :key="shift.id" class="size-2.5 border" :class="chipTone(shift)"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <p v-if="!schedule.shifts.length" class="text-muted-foreground text-center text-sm">
            <template v-if="viewer.role === 'scheduler'">Empty month. Click a day to put the first shift on it.</template>
            <template v-else>{{ schedule.name }} has no shifts yet.</template>
        </p>

        <!-- Day detail. Everything the grid could not show lives here, including
             every authoring affordance — which is how the grid stays legible. -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{{ openDay ? dayLong(openDay) : '' }}</SheetTitle>
                </SheetHeader>

                <div class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="shift in daySheetShifts"
                        :key="shift.id"
                        class="border-input flex flex-col gap-2 border p-3"
                        :class="isMine(shift) ? 'border-rom-slate bg-rom-slate-50/60' : ''"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-rom-ink font-medium">{{ shift.kind ?? 'Shift' }}</span>
                            <span class="text-muted-foreground font-mono text-sm">{{ timeRange(shift) }}</span>
                        </div>

                        <Badge v-if="shift.foreignGroup" variant="info" class="self-start">{{ shift.foreignGroup }} · open to you</Badge>

                        <p class="text-sm" :class="isFull(shift) ? 'text-muted-foreground' : 'text-success font-medium'">
                            {{ shift.signups.length }}/{{ shift.capacity }} filled<template v-if="!isFull(shift)">
                                · {{ remaining(shift) }} open</template
                            >
                        </p>

                        <ul v-if="canSeeNames(viewer) && shift.signups.length" class="flex flex-col gap-1 text-sm">
                            <li v-for="signup in shift.signups" :key="signup.person.id" class="flex items-center gap-2">
                                <span :class="signup.person.id === 1 ? 'text-rom-slate font-medium' : 'text-rom-ink'">{{ signup.person.name }}</span>
                                <span v-if="signup.assigned" class="text-muted-foreground text-xs">assigned</span>
                                <button
                                    v-if="canAuthor(shift, viewer)"
                                    type="button"
                                    class="text-destructive-tint-foreground ml-auto text-xs hover:underline"
                                    @click="removeSignup(shift, signup.person.id)"
                                >
                                    remove
                                </button>
                            </li>
                        </ul>

                        <div class="flex flex-wrap gap-2">
                            <Button v-if="isMine(shift)" size="sm" variant="secondary" @click="drop(shift)">Drop</Button>
                            <Button v-else-if="canTake(shift, viewer)" size="sm" @click="take(shift)">Sign up</Button>
                            <template v-if="canAuthor(shift, viewer)">
                                <Button size="sm" variant="ghost">Assign…</Button>
                                <Button size="sm" variant="ghost">Edit</Button>
                                <Button size="sm" variant="ghost">Delete</Button>
                            </template>
                        </div>
                    </div>

                    <p v-if="!daySheetShifts.length" class="text-muted-foreground py-6 text-center text-sm">Nothing scheduled on this day.</p>

                    <Button v-if="viewer.role === 'scheduler'" class="gap-1.5 self-start"><PhPlus class="size-4" /> Add a shift to this day</Button>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
