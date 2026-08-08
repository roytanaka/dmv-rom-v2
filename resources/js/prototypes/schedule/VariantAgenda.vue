<script setup lang="ts">
// PROTOTYPE VARIANT A — "Agenda". See #330.
//
// One vertical list, grouped by day, sticky day headings. The row is the unit: time,
// kind, k/n, who is on it, and the affordance. No grid, no matrix — the same thing
// on a phone and on a desktop, wider rather than different.
//
// The bet: a Schedule is read one day at a time ("am I on this week?"), so reading
// order should be chronological and nothing should need decoding. The cost is that
// the shape of a month is invisible — you cannot see "the last week is bare" without
// scrolling it.
//
// Foreign (`open`) Shifts get their own band at the foot of each day rather than
// being interleaved, so a Gallery Interpreter's month never fills with someone
// else's Shifts.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PhCaretRight, PhPencilSimple, PhPlus, PhTrash, PhUserPlus } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';
import {
    byDay,
    canAuthor,
    canSeeNames,
    canTake,
    dayLong,
    drop,
    isFull,
    isMine,
    rangeLabel,
    remaining,
    removeSignup,
    take,
    timeRange,
    totals,
} from './helpers';
import type { Schedule, Shift, Viewer } from './types';

const props = defineProps<{ schedule: Schedule; viewer: Viewer; groupName: string; showForeign: boolean }>();

type Filter = 'all' | 'open' | 'mine';
const filter = ref<Filter>('all');

const visible = computed(() => props.schedule.shifts.filter((s) => (s.foreignGroup === undefined ? true : props.showForeign)));

const passesFilter = (shift: Shift) => (filter.value === 'open' ? !isFull(shift) : filter.value === 'mine' ? isMine(shift) : true);

const days = computed(() =>
    byDay(visible.value.filter(passesFilter))
        .map((bucket) => ({
            date: bucket.date,
            own: bucket.shifts.filter((s) => s.foreignGroup === undefined),
            foreign: bucket.shifts.filter((s) => s.foreignGroup !== undefined),
        }))
        .filter((bucket) => bucket.own.length || bucket.foreign.length),
);

const counts = computed(() => totals(props.schedule.shifts.filter((s) => s.foreignGroup === undefined)));

// A row's tone. `mine` is the loudest thing on the page — the reason most people open
// a Schedule is to check their own dates.
const rowTone = (shift: Shift) =>
    isMine(shift)
        ? 'border-l-rom-slate bg-rom-slate-50/70'
        : isFull(shift)
          ? 'border-l-transparent bg-muted/40'
          : 'border-l-success/60 bg-background';
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Schedule header. Name and description are as-authored content (#324). -->
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 class="text-rom-ink text-2xl font-semibold">{{ schedule.name }}</h2>
                <Badge v-if="schedule.state === 'draft'" variant="warning">Draft</Badge>
                <span class="text-muted-foreground text-sm">{{ rangeLabel(schedule) }}</span>
            </div>
            <p v-if="schedule.description" class="text-rom-ink max-w-2xl text-base">{{ schedule.description }}</p>
            <p class="text-muted-foreground text-sm">
                {{ counts.shifts }} shifts · <span :class="counts.open ? 'text-success font-medium' : ''">{{ counts.open }} slots open</span>
                <template v-if="viewer.role !== 'nonmember'"> · you're on {{ counts.mine }}</template>
            </p>
            <p v-if="viewer.role === 'nonmember'" class="text-muted-foreground text-sm italic">
                You're not a member of {{ groupName }} — this schedule is read-only for you.
            </p>
        </div>

        <!-- Scheduler bar. Inline on the same surface (#328), not a separate screen. -->
        <div v-if="viewer.role === 'scheduler'" class="border-rom-slate/30 bg-rom-slate-50 flex flex-wrap items-center gap-2 border p-3">
            <Button size="sm" class="gap-1.5"><PhPlus class="size-4" /> Add shift</Button>
            <Button size="sm" variant="secondary">Copy from another schedule</Button>
            <Button size="sm" variant="ghost">Edit schedule</Button>
            <span class="text-muted-foreground ml-auto text-xs">You're the Scheduler for {{ groupName }}</span>
        </div>

        <!-- Filters. Three states, no dropdown — the whole set is visible at once. -->
        <div v-if="schedule.shifts.length" class="flex flex-wrap items-center gap-2">
            <button
                v-for="option in ['all', 'open', 'mine'] as Filter[]"
                :key="option"
                type="button"
                class="border px-3 py-1.5 text-sm transition-colors"
                :class="filter === option ? 'border-rom-ink bg-rom-ink text-white' : 'border-input text-muted-foreground hover:text-rom-ink'"
                @click="filter = option"
            >
                {{ option === 'all' ? 'All shifts' : option === 'open' ? 'Open slots' : 'My shifts' }}
            </button>
        </div>

        <!-- Empty state (#330 point 4) — no legacy prior art; legacy renders nothing
             until a Schedule is generated. What a Scheduler actually needs here is a
             way out of typing 60 rows, which is #329's question, not this one. -->
        <div v-if="!schedule.shifts.length" class="border-input flex flex-col items-center gap-3 border border-dashed px-6 py-16 text-center">
            <p class="text-rom-ink text-lg font-medium">No shifts yet</p>
            <p class="text-muted-foreground max-w-md text-sm">
                {{ schedule.name }} is a draft. Nobody can see it until it's published, and there's nothing to see until it has shifts.
            </p>
            <div v-if="viewer.role === 'scheduler'" class="mt-2 flex flex-wrap justify-center gap-2">
                <Button class="gap-1.5"><PhPlus class="size-4" /> Add the first shift</Button>
                <Button variant="secondary">Copy from November 2026</Button>
            </div>
            <p v-else class="text-muted-foreground text-sm">Your Scheduler is still building it.</p>
        </div>

        <p v-else-if="!days.length" class="text-muted-foreground py-12 text-center">Nothing matches that filter.</p>

        <!-- The list. -->
        <div v-for="day in days" :key="day.date" class="flex flex-col">
            <h3 class="bg-background border-input text-rom-ink sticky top-32 z-10 border-b py-2 text-sm font-semibold tracking-wide uppercase">
                {{ dayLong(day.date) }}
            </h3>

            <div class="flex flex-col gap-px py-2">
                <div
                    v-for="shift in day.own"
                    :key="shift.id"
                    class="flex flex-col gap-2 border-l-4 px-3 py-3 sm:flex-row sm:items-center sm:gap-4"
                    :class="rowTone(shift)"
                >
                    <span class="text-rom-ink w-32 shrink-0 font-mono text-sm">{{ timeRange(shift) }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="text-rom-ink block font-medium">{{ shift.kind ?? 'Shift' }}</span>
                        <span v-if="canSeeNames(viewer) && shift.signups.length" class="text-muted-foreground block text-sm">
                            <template v-for="(signup, i) in shift.signups" :key="signup.person.id">
                                <span v-if="i">, </span>
                                <span :class="signup.person.id === 1 ? 'text-rom-slate font-medium' : ''">{{ signup.person.name }}</span>
                                <span v-if="signup.assigned" class="text-muted-foreground/70 text-xs"> (assigned)</span>
                                <button
                                    v-if="canAuthor(shift, viewer)"
                                    type="button"
                                    class="text-destructive-tint-foreground ml-0.5 text-xs hover:underline"
                                    @click="removeSignup(shift, signup.person.id)"
                                >
                                    ×
                                </button>
                            </template>
                        </span>
                    </span>

                    <span class="shrink-0 text-sm" :class="isFull(shift) ? 'text-muted-foreground' : 'text-success font-medium'">
                        {{ shift.signups.length }}/{{ shift.capacity }}
                        <span v-if="!isFull(shift)" class="text-muted-foreground">· {{ remaining(shift) }} open</span>
                    </span>

                    <span class="flex shrink-0 items-center gap-1">
                        <Button v-if="isMine(shift)" size="sm" variant="secondary" @click="drop(shift)">Drop</Button>
                        <Button v-else-if="canTake(shift, viewer)" size="sm" @click="take(shift)">Sign up</Button>
                        <template v-if="canAuthor(shift, viewer)">
                            <Button size="sm" variant="ghost" class="px-2" title="Assign a volunteer"><PhUserPlus class="size-4" /></Button>
                            <Button size="sm" variant="ghost" class="px-2" title="Edit shift"><PhPencilSimple class="size-4" /></Button>
                            <Button size="sm" variant="ghost" class="px-2" title="Delete shift"><PhTrash class="size-4" /></Button>
                        </template>
                    </span>
                </div>

                <!-- Foreign band: another Group's `open` Shifts, kept out of the flow. -->
                <div v-if="day.foreign.length" class="border-rom-slate/40 mt-2 border border-dashed p-3">
                    <p class="text-muted-foreground mb-2 flex items-center gap-1 text-xs font-semibold tracking-wide uppercase">
                        <PhCaretRight class="size-3" /> Open to you — {{ day.foreign[0].foreignGroup }}
                    </p>
                    <div v-for="shift in day.foreign" :key="shift.id" class="flex flex-wrap items-center gap-3 py-1.5">
                        <span class="text-rom-ink w-32 shrink-0 font-mono text-sm">{{ timeRange(shift) }}</span>
                        <span class="text-rom-ink flex-1 text-sm">{{ shift.kind }}</span>
                        <Badge variant="secondary" class="shrink-0">{{ shift.foreignGroup }}</Badge>
                        <span class="text-muted-foreground shrink-0 text-sm">{{ shift.signups.length }}/{{ shift.capacity }}</span>
                        <Button v-if="isMine(shift)" size="sm" variant="secondary" @click="drop(shift)">Drop</Button>
                        <Button v-else-if="canTake(shift, viewer)" size="sm" variant="secondary" @click="take(shift)">Sign up</Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
