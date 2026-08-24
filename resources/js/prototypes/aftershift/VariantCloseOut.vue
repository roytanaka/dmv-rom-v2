<script setup lang="ts">
// VARIANT B — a close-out view for a Schedule. PROTOTYPE (#405), throwaway.
//
// Option 2 from the ticket. A nested route under the Scheduling section — the escape
// hatch ADR-0021 §6 reserves — holding every past seat in one table an officer works
// down. It matches how a month probably actually gets closed, and it is the only
// variant where "which shifts are still blank" is the page's whole subject.
//
// It is an officer surface and nothing else, which is the finding: on its own it leaves
// the volunteer with nowhere to sign out, so it can only ever be half an answer.
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhLock } from '@phosphor-icons/vue';
import { computed, reactive, ref } from 'vue';
import PrototypeNote from './PrototypeNote.vue';
import { allPastSeats, formatShortDay, timeRange } from './fixtures';
import type { GroupFixture, SignUp, Viewer } from './types';

const props = defineProps<{ group: GroupFixture; viewer: Viewer }>();

const blanksOnly = ref(false);

const seats = computed(() => allPastSeats(props.group));
const shown = computed(() => (blanksOnly.value ? seats.value.filter((row) => !row.recorded) : seats.value));
const missing = computed(() => seats.value.filter((row) => !row.recorded).length);

const drafts = reactive<Record<number, { visitors: string; extra: string }>>({});

const draftFor = (signUp: SignUp) => {
    drafts[signUp.id] ??= {
        visitors: signUp.visitorCount === null ? '' : String(signUp.visitorCount),
        extra: signUp.extraInteractionCount === null ? '' : String(signUp.extraInteractionCount),
    };
    return drafts[signUp.id];
};

// Save the whole page, not a row at a time — the officer is typing down a column.
const saveAll = () => {
    for (const { signUp } of seats.value) {
        const draft = draftFor(signUp);
        if (draft.visitors.trim() !== '') signUp.visitorCount = Number(draft.visitors);
        if (props.group.collects === 'two' && draft.extra.trim() !== '') signUp.extraInteractionCount = Number(draft.extra);
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <PrototypeNote
            surface="Group → Scheduling tab → a Schedule → Close out"
            url="/groups/{slug}/scheduling/{id}/close-out"
            prompt="A count on the Scheduling tab itself — 'n shifts still need a number'. The officer is told; the volunteer is not."
            cost="A second scheduling route, and an officer-only one. Volunteers still need somewhere to sign out, so this variant never ships alone."
        />

        <!-- Not the viewer's page unless they hold the role. Shown rather than hidden so
             the gate is part of what gets judged. -->
        <div
            v-if="viewer.role !== 'officer'"
            class="border-input text-muted-foreground flex flex-col gap-2 rounded-md border border-dashed p-6 text-sm"
        >
            <span class="text-rom-ink flex items-center gap-2 font-medium"><PhLock class="size-4" /> Close-out is for Schedulers and Chairs.</span>
            <span
                >Switch the viewer to Officer in the bar below. As a volunteer you would never reach this page — which is the point being
                tested.</span
            >
        </div>

        <template v-else>
            <header class="flex flex-col gap-1">
                <p class="text-muted-foreground text-xs">{{ group.groupName }} · Scheduling · {{ group.scheduleName }}</p>
                <h2 class="text-rom-ink text-lg font-semibold">Close out</h2>
                <p v-if="group.collects === 'none'" class="text-muted-foreground text-sm">
                    {{ group.groupName }} does not collect a visitor count, so there is nothing to close out.
                </p>
                <p v-else class="text-muted-foreground text-sm">
                    <span class="text-rom-ink font-medium tabular-nums">{{ missing }}</span> of
                    <span class="tabular-nums">{{ seats.length }}</span> worked shifts still need a number.
                </p>
            </header>

            <template v-if="group.collects !== 'none'">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Checkbox id="b-blanks" v-model:checked="blanksOnly" />
                        <Label for="b-blanks" class="text-sm font-normal">Only shifts with no number</Label>
                    </div>
                    <Button type="button" size="sm" @click="saveAll">Save all</Button>
                </div>

                <!-- One table, scrollable on a phone. Deliberately dense: the officer is
                     reading a paper list and typing down a column. -->
                <div class="border-border overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/60 text-muted-foreground">
                            <tr class="text-left">
                                <th scope="col" class="px-3 py-2 font-medium">Day</th>
                                <th scope="col" class="px-3 py-2 font-medium">Shift</th>
                                <th scope="col" class="px-3 py-2 font-medium">Member</th>
                                <th scope="col" class="px-3 py-2 font-medium">{{ group.visitorLabel }}</th>
                                <th v-if="group.collects === 'two'" scope="col" class="px-3 py-2 font-medium">{{ group.extraLabel }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in shown"
                                :key="row.signUp.id"
                                class="border-border border-t"
                                :class="row.recorded ? '' : 'bg-rom-ink/[0.03]'"
                            >
                                <td class="text-muted-foreground px-3 py-2 whitespace-nowrap">{{ formatShortDay(row.shift.startsAt) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="text-rom-ink">{{ row.shift.kind }}</span>
                                    <span class="text-muted-foreground ml-2 text-xs">{{ timeRange(row.shift) }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ row.signUp.memberName }}</td>
                                <td class="px-3 py-2">
                                    <Input
                                        v-model="draftFor(row.signUp).visitors"
                                        type="number"
                                        min="0"
                                        class="h-8 w-20 tabular-nums"
                                        placeholder="—"
                                    />
                                </td>
                                <td v-if="group.collects === 'two'" class="px-3 py-2">
                                    <Input v-model="draftFor(row.signUp).extra" type="number" min="0" class="h-8 w-20 tabular-nums" placeholder="—" />
                                </td>
                            </tr>
                            <tr v-if="!shown.length">
                                <td colspan="5" class="text-muted-foreground px-3 py-6 text-center">Every worked shift has a number.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="border-input text-muted-foreground border border-dashed p-3 text-xs">
                    This page closes out <span class="text-rom-ink">{{ group.scheduleName }}</span> only. Last month's blanks are on last month's
                    close-out page, so "what is still missing across the year" is a question this variant cannot answer.
                </p>
            </template>
        </template>
    </div>
</template>
