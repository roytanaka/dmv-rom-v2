<script setup lang="ts">
// VARIANT D — on the Hours tab. PROTOTYPE (#405), throwaway.
//
// Option 4, added by the 2026-08-24 amendment. Spec #406 shipped a Hours section tab on
// every Group and sub-Group, carrying a Member-facing write form and the officer's
// reports. This variant puts the visitor count on that tab: after-shift data sits beside
// the hours it relates to, on a surface that already exists.
//
// The layout below deliberately mirrors the real GroupHours component — report link,
// entry card, own-records card — with one card added on top. The mock hours form is
// inert on purpose: #405 says do not prototype an hours input.
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhChartBar, PhClockCountdown, PhLock, PhWarningCircle } from '@phosphor-icons/vue';
import { computed, reactive } from 'vue';
import CountFields from './CountFields.vue';
import PrototypeNote from './PrototypeNote.vue';
import { formatShortDay, outstandingFor, timeRange } from './fixtures';
import type { GroupFixture, SignUp, Viewer } from './types';

const props = defineProps<{ group: GroupFixture; viewer: Viewer }>();

const rows = computed(() => (props.group.collects === 'none' ? [] : outstandingFor(props.group, props.viewer)));
const toRecord = computed(() => rows.value.filter((row) => !row.recorded && !row.closed));
const closedRows = computed(() => rows.value.filter((row) => !row.recorded && row.closed));

const drafts = reactive<Record<number, { visitors: string; extra: string }>>({});

const draftFor = (signUp: SignUp) => {
    drafts[signUp.id] ??= { visitors: '', extra: '' };
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
};
</script>

<template>
    <div class="flex max-w-3xl flex-col gap-5">
        <PrototypeNote
            surface="Group → Hours tab (the surface spec #406 already built)"
            url="/groups/{slug}/hours"
            prompt="A count on the Hours tab, next to the hours. It rides on a tab a Member already visits once a month."
            cost="Scheduling data on a tab reached for a different reason, on every Group including those that run no scheduling. The volunteer is on the Schedule at sign-out, not here."
        />

        <header class="flex flex-col gap-1">
            <p class="text-muted-foreground text-xs">{{ group.groupName }} · Hours</p>
        </header>

        <div v-if="viewer.role === 'officer'" class="flex flex-wrap gap-2">
            <span class="text-rom-ink border-border inline-flex w-fit items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium">
                <PhChartBar class="size-4" />
                View fiscal-year report
            </span>
            <span
                v-if="group.collects !== 'none'"
                class="text-rom-ink border-border inline-flex w-fit items-center gap-2 rounded-md border border-dashed px-3 py-1.5 text-sm font-medium"
            >
                <PhWarningCircle class="size-4" />
                Shifts missing a visitor count
            </span>
        </div>

        <!-- THE NEW CARD. Everything below it already exists on staging. -->
        <Card v-if="group.collects !== 'none'" class="border-rom-ink/30">
            <CardHeader>
                <CardTitle class="text-sm font-semibold tracking-wide uppercase">Shifts to record</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p v-if="!toRecord.length && !closedRows.length" class="text-muted-foreground text-sm">
                    Every shift you have worked for {{ group.groupName }} has a number.
                </p>

                <div
                    v-for="row in toRecord"
                    :key="row.signUp.id"
                    class="border-border flex flex-wrap items-end gap-3 border-t pt-4 first:border-t-0 first:pt-0"
                >
                    <div class="flex min-w-44 flex-col gap-0.5">
                        <span class="text-rom-ink font-medium">{{ row.shift.kind }}</span>
                        <span class="text-muted-foreground text-xs">{{ formatShortDay(row.shift.startsAt) }} · {{ timeRange(row.shift) }}</span>
                        <span class="text-muted-foreground flex items-center gap-1 text-xs">
                            <PhClockCountdown class="size-3.5" />
                            {{ row.daysLeft }} days left
                        </span>
                    </div>
                    <CountFields
                        :group="group"
                        :id-prefix="`d-${row.signUp.id}`"
                        inline
                        v-model:visitors="draftFor(row.signUp).visitors"
                        v-model:extra="draftFor(row.signUp).extra"
                    />
                    <Button type="button" size="sm" :disabled="!complete(row.signUp)" @click="save(row.signUp)">Save</Button>
                </div>

                <div v-for="row in closedRows" :key="`closed-${row.signUp.id}`" class="border-border text-muted-foreground border-t pt-4 text-sm">
                    <span class="flex flex-wrap items-center gap-2">
                        <PhLock class="size-4" />
                        {{ formatShortDay(row.shift.startsAt) }} · {{ row.shift.kind }} — closed. Ask a {{ group.groupName }} officer.
                    </span>
                </div>
            </CardContent>
        </Card>

        <!-- Existing #406 surface, drawn inert so the new card can be judged in place. -->
        <Card class="opacity-70">
            <CardHeader>
                <CardTitle class="text-sm font-semibold tracking-wide uppercase">Record extra hours</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p class="text-muted-foreground text-sm">Scheduled shifts and meetings are already counted. Do not add them again.</p>
                <div
                    v-for="month in ['August 2026', 'July 2026']"
                    :key="month"
                    class="border-border flex flex-wrap items-end gap-3 border-t pt-4 first:border-t-0 first:pt-0"
                >
                    <div class="flex min-w-40 flex-col gap-0.5">
                        <span class="text-rom-ink font-medium">{{ month }}</span>
                        <span class="text-muted-foreground text-xs">4 hours on file</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label :for="`d-hours-${month}`" class="text-xs">Hours to add</Label>
                        <Input :id="`d-hours-${month}`" type="number" class="w-28" disabled />
                    </div>
                    <Button type="button" disabled>Add</Button>
                </div>
                <p class="text-muted-foreground text-xs italic">Existing surface, drawn inert — #405 says do not prototype an hours input.</p>
            </CardContent>
        </Card>
    </div>
</template>
