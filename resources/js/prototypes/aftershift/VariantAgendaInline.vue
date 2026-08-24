<script setup lang="ts">
// VARIANT A — inline on the Agenda. PROTOTYPE (#405), throwaway.
//
// Option 1 from the ticket. The Scheduling tab is unchanged except that a Shift you
// hold a seat on grows a sign-out panel once it is nearly over. Cheapest possible
// answer: no new route, no new destination, no new nav.
//
// What tells you something is waiting: nothing. You come back to the Schedule you signed
// up on. That is the variant's whole argument and its whole weakness.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { PhCheckCircle, PhPencilSimple, PhWarningCircle } from '@phosphor-icons/vue';
import { computed, reactive } from 'vue';
import CountFields from './CountFields.vue';
import PrototypeNote from './PrototypeNote.vue';
import { daysSinceEnd, formatDay, isRecorded, signOutOpen, timeRange, WINDOW_DAYS } from './fixtures';
import type { GroupFixture, Shift, SignUp, Viewer } from './types';

const props = defineProps<{ group: GroupFixture; viewer: Viewer }>();

// One Schedule at a time — a permalink opens one Schedule, and a Shift belongs to
// exactly one (ADR-0021 §1, §6). Anything older than this Schedule's range is on a
// different page entirely, which the footnote below makes visible.
const inThisSchedule = computed(() => props.group.shifts.filter((shift) => shift.scheduleName === props.group.scheduleName));

const elsewhere = computed(() =>
    props.group.shifts
        .filter((shift) => shift.scheduleName !== props.group.scheduleName)
        .filter((shift) => {
            const seat = shift.signUps.find((s) => s.memberId === props.viewer.memberId);
            return seat && !isRecorded(props.group, seat);
        }),
);

// Day-grouped, ascending — the Agenda's own order (ADR-0021 §7).
const days = computed(() => {
    const buckets = new Map<string, Shift[]>();
    for (const shift of [...inThisSchedule.value].sort((a, b) => a.startsAt.localeCompare(b.startsAt))) {
        const key = shift.startsAt.slice(0, 10);
        buckets.set(key, [...(buckets.get(key) ?? []), shift]);
    }
    return [...buckets.entries()].map(([key, shifts]) => ({ key, label: formatDay(shifts[0].startsAt), shifts }));
});

const mySeat = (shift: Shift) => shift.signUps.find((s) => s.memberId === props.viewer.memberId) ?? null;

// One draft per seat. The officer can open any seat; a volunteer only ever opens theirs.
const drafts = reactive<Record<number, { visitors: string; extra: string; editing: boolean }>>({});

const draftFor = (signUp: SignUp) => {
    drafts[signUp.id] ??= {
        visitors: signUp.visitorCount === null ? '' : String(signUp.visitorCount),
        extra: signUp.extraInteractionCount === null ? '' : String(signUp.extraInteractionCount),
        editing: false,
    };
    return drafts[signUp.id];
};

const complete = (signUp: SignUp) => {
    const draft = draftFor(signUp);
    if (draft.visitors.trim() === '') return false;
    return props.group.collects !== 'two' || draft.extra.trim() !== '';
};

const save = (signUp: SignUp) => {
    const draft = draftFor(signUp);
    signUp.visitorCount = Number(draft.visitors);
    signUp.extraInteractionCount = props.group.collects === 'two' ? Number(draft.extra) : null;
    draft.editing = false;
};

const closed = (shift: Shift) => daysSinceEnd(shift) >= WINDOW_DAYS;

const daysLeft = (shift: Shift) => WINDOW_DAYS - Math.max(0, daysSinceEnd(shift));

const summary = (signUp: SignUp) =>
    props.group.collects === 'two' ? `${signUp.visitorCount} on tour · ${signUp.extraInteractionCount} others` : `${signUp.visitorCount} visitors`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <PrototypeNote
            surface="Group → Scheduling tab → a Schedule"
            url="/groups/{slug}/scheduling/{id}"
            prompt="Nothing. You return to the Schedule you signed up on, and the panel is there when the shift is nearly over."
            cost="No new surface at all. But the entry point is a Schedule, and a volunteer who works three Groups has three Schedules to remember."
        />

        <header class="flex flex-col gap-1">
            <p class="text-muted-foreground text-xs">{{ group.groupName }} · Scheduling</p>
            <h2 class="text-rom-ink text-lg font-semibold">{{ group.scheduleName }}</h2>
        </header>

        <section v-for="day in days" :key="day.key" class="flex flex-col gap-2">
            <h3 class="text-rom-ink border-border border-b pb-1 text-sm font-semibold">{{ day.label }}</h3>

            <Card v-for="shift in day.shifts" :key="shift.id">
                <CardContent class="flex flex-col gap-2 py-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <span class="text-rom-ink font-medium">{{ timeRange(shift) }}</span>
                            <span class="text-muted-foreground text-sm">{{ shift.kind }}</span>
                        </div>
                        <span class="text-muted-foreground text-sm tabular-nums">{{ shift.signUps.length }} of {{ shift.capacity }} taken</span>
                    </div>

                    <!-- Seats. An officer sees each seat's recorded number and a pencil to
                         correct it; everyone else sees names only, as they do today. -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge v-for="signUp in shift.signUps" :key="signUp.id" variant="secondary" class="gap-1.5 font-normal">
                            {{ signUp.memberName }}
                            <template v-if="group.collects !== 'none' && signOutOpen(shift) && viewer.role === 'officer'">
                                <span class="tabular-nums" :class="isRecorded(group, signUp) ? 'text-rom-ink' : 'text-muted-foreground italic'">
                                    {{ isRecorded(group, signUp) ? summary(signUp) : 'no number' }}
                                </span>
                                <button
                                    type="button"
                                    class="hover:text-rom-ink -mr-0.5"
                                    aria-label="Correct this count"
                                    @click="draftFor(signUp).editing = !draftFor(signUp).editing"
                                >
                                    <PhPencilSimple class="size-3.5" />
                                </button>
                            </template>
                        </Badge>
                    </div>

                    <!-- The officer's correction, opened per seat, inline. No deadline. -->
                    <div
                        v-for="signUp in shift.signUps.filter((s) => drafts[s.id]?.editing)"
                        :key="`edit-${signUp.id}`"
                        class="border-border bg-muted/40 flex flex-wrap items-end gap-3 rounded-md border p-3"
                    >
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground text-xs">Correcting {{ signUp.memberName }}</span>
                            <CountFields
                                :group="group"
                                :id-prefix="`a-officer-${signUp.id}`"
                                inline
                                v-model:visitors="draftFor(signUp).visitors"
                                v-model:extra="draftFor(signUp).extra"
                            />
                        </div>
                        <Button type="button" size="sm" @click="save(signUp)">Save</Button>
                    </div>

                    <!-- The volunteer's own sign-out, on their own seat, once the shift is
                         nearly over. Required at the surface: the button stays disabled
                         until a number is typed — the single design fact #404 found. -->
                    <template v-if="group.collects !== 'none' && mySeat(shift) && signOutOpen(shift)">
                        <div
                            v-if="isRecorded(group, mySeat(shift)!)"
                            class="text-muted-foreground flex flex-wrap items-center gap-2 text-sm"
                            data-state="recorded"
                        >
                            <PhCheckCircle class="text-rom-ink size-4" />
                            <span>You signed out: {{ summary(mySeat(shift)!) }}</span>
                        </div>

                        <div
                            v-else-if="closed(shift)"
                            class="border-border text-muted-foreground flex flex-col gap-1 rounded-md border border-dashed p-3 text-sm"
                        >
                            <span class="flex items-center gap-2"
                                ><PhWarningCircle class="size-4" /> This shift closed for sign-out on the 28th day.</span
                            >
                            <span>Ask a {{ group.groupName }} officer to add your number.</span>
                        </div>

                        <div v-else class="border-rom-ink/20 bg-muted/40 flex flex-col gap-3 rounded-md border p-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <CountFields
                                    :group="group"
                                    :id-prefix="`a-mine-${shift.id}`"
                                    inline
                                    v-model:visitors="draftFor(mySeat(shift)!).visitors"
                                    v-model:extra="draftFor(mySeat(shift)!).extra"
                                />
                                <Button type="button" size="sm" :disabled="!complete(mySeat(shift)!)" @click="save(mySeat(shift)!)">
                                    Sign out
                                </Button>
                            </div>
                            <p class="text-muted-foreground text-xs">{{ daysLeft(shift) }} days left to fill this in.</p>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </section>

        <!-- The cost the fixture makes concrete: a Shift belongs to one Schedule, so a
             shift older than this month's range is not on this page at all. On this
             variant nothing tells the volunteer it exists — this footnote is prototype
             chrome, not a proposed feature. -->
        <p v-if="elsewhere.length" class="border-input text-muted-foreground border border-dashed p-3 text-xs">
            Not shown, and nothing on this page would tell you:
            <span class="text-rom-ink">{{ elsewhere.length }}</span> shift(s) of yours still without a number sit on the
            <span class="text-rom-ink">{{ elsewhere[0].scheduleName }}</span> Schedule, a separate page you would have to know to go looking for.
        </p>
    </div>
</template>
