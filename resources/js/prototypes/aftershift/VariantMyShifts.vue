<script setup lang="ts">
// VARIANT C — the volunteer's own list, on My Calendar. PROTOTYPE (#405), throwaway.
//
// Option 3 from the ticket, and cheaper than the ticket assumed. The destination is not
// new: `nav.personal.calendar` is already in the top bar as a `ComingSoon` stub
// (routes/web.php, Zone A), sitting beside the `hours` stub that spec #406 has just
// turned into the real My Hours page. ADR-0021 §7 named this surface itself — "no 'My
// shifts' filter on a Group's Schedule … that is My Calendar's question".
//
// It holds every shift *you* worked that still wants a number, across every Group,
// because a volunteer who takes another Group's `open` Shift has no reason to go looking
// for that Group's Schedule (ADR-0021 §4). It is the only variant that can carry a
// prompt, and the only one where the 28-day window reads as a countdown rather than as a
// surprise.
//
// It is a volunteer surface and nothing else: an officer correcting somebody else's
// number has to be somewhere other than here.
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { PhCaretDown, PhCheckCircle, PhClockCountdown, PhLock } from '@phosphor-icons/vue';
import { computed, reactive } from 'vue';
import CountFields from './CountFields.vue';
import PrototypeNote from './PrototypeNote.vue';
import { FOREIGN_OUTSTANDING, formatShortDay, outstandingFor, timeRange } from './fixtures';
import type { GroupFixture, SignUp, Viewer } from './types';

const props = defineProps<{ group: GroupFixture; viewer: Viewer }>();

const rows = computed(() => (props.group.collects === 'none' ? [] : outstandingFor(props.group, props.viewer)));

const signOutNow = computed(() => rows.value.filter((row) => !row.recorded && row.daysAgo === 0));
const waiting = computed(() => rows.value.filter((row) => !row.recorded && !row.closed && row.daysAgo > 0));
const closed = computed(() => rows.value.filter((row) => !row.recorded && row.closed));
const done = computed(() => rows.value.filter((row) => row.recorded));

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

const summary = (signUp: SignUp) =>
    props.group.collects === 'two' ? `${signUp.visitorCount} on tour · ${signUp.extraInteractionCount} others` : `${signUp.visitorCount} visitors`;

const empty = computed(() => !signOutNow.value.length && !waiting.value.length && !closed.value.length);
</script>

<template>
    <div class="flex flex-col gap-5">
        <PrototypeNote
            surface="Top bar → My Calendar — the existing Zone A stub, beside My Hours"
            url="/calendar"
            prompt="The destination itself, with a count on it. The only variant that can carry a prompt, and the only one that spans Groups."
            cost="A real notion of an outstanding task, and it fills a stub earmarked for the whole of a Member's own schedule. Officers still need a correction surface elsewhere."
        />

        <header class="flex flex-col gap-1">
            <h2 class="text-rom-ink text-lg font-semibold">My calendar</h2>
            <p class="text-muted-foreground text-sm">Shifts you have worked, and the numbers still to fill in.</p>
            <p class="text-muted-foreground text-xs italic">
                Prototype scope: only the after-shift half is drawn. The rest of My Calendar — upcoming shifts, meetings — is a separate question.
            </p>
        </header>

        <div v-if="group.collects === 'none'" class="text-muted-foreground border-input rounded-md border border-dashed p-6 text-sm">
            {{ group.groupName }} does not collect a visitor count, so nothing you work there appears here.
        </div>

        <template v-else>
            <!-- 1. The shift happening now. The whole reason the page can carry the
                 forcing function: a volunteer walking out of the building opens this on
                 their phone, and it opens on the thing they are here to do. -->
            <section v-if="signOutNow.length" class="flex flex-col gap-2">
                <h3 class="text-rom-ink text-sm font-semibold">Sign out</h3>
                <Card v-for="row in signOutNow" :key="row.signUp.id" class="border-rom-ink/30">
                    <CardContent class="flex flex-col gap-3 py-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-rom-ink font-medium">{{ row.shift.kind }} · {{ group.groupName }}</span>
                            <span class="text-muted-foreground text-sm">{{ formatShortDay(row.shift.startsAt) }} · {{ timeRange(row.shift) }}</span>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <CountFields
                                :group="group"
                                :id-prefix="`c-now-${row.signUp.id}`"
                                v-model:visitors="draftFor(row.signUp).visitors"
                                v-model:extra="draftFor(row.signUp).extra"
                            />
                            <Button type="button" :disabled="!complete(row.signUp)" @click="save(row.signUp)">Sign out</Button>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- 2. Past shifts still inside the 28-day window, newest first, each one
                 saying how long is left. Legacy simply stopped showing these. -->
            <section v-if="waiting.length" class="flex flex-col gap-2">
                <h3 class="text-rom-ink text-sm font-semibold">Still needs a number</h3>
                <Card v-for="row in waiting" :key="row.signUp.id">
                    <CardContent class="flex flex-col gap-3 py-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-rom-ink font-medium">{{ row.shift.kind }} · {{ group.groupName }}</span>
                                <span class="text-muted-foreground text-sm"
                                    >{{ formatShortDay(row.shift.startsAt) }} · {{ timeRange(row.shift) }}</span
                                >
                            </div>
                            <span class="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <PhClockCountdown class="size-4" />
                                {{ row.daysLeft }} days left
                            </span>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <CountFields
                                :group="group"
                                :id-prefix="`c-wait-${row.signUp.id}`"
                                inline
                                v-model:visitors="draftFor(row.signUp).visitors"
                                v-model:extra="draftFor(row.signUp).extra"
                            />
                            <Button type="button" size="sm" :disabled="!complete(row.signUp)" @click="save(row.signUp)">Save</Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Another Group's `open` Shift the viewer took. Hardcoded so the
                     cross-Group case is visible; only this variant can show it at all. -->
                <Card class="border-dashed">
                    <CardContent class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-rom-ink font-medium">{{ FOREIGN_OUTSTANDING.kind }} · {{ FOREIGN_OUTSTANDING.groupName }}</span>
                            <span class="text-muted-foreground text-sm">{{ FOREIGN_OUTSTANDING.when }} · an open shift you took</span>
                        </div>
                        <span class="text-muted-foreground text-xs">{{ FOREIGN_OUTSTANDING.daysLeft }} days left</span>
                    </CardContent>
                </Card>
            </section>

            <!-- 3. Past the window. Legacy said nothing at all here; #404 rejected the
                 silence, so the page has to show what "too late" looks like. -->
            <section v-if="closed.length" class="flex flex-col gap-2">
                <h3 class="text-muted-foreground text-sm font-semibold">Closed</h3>
                <Card v-for="row in closed" :key="row.signUp.id" class="opacity-70">
                    <CardContent class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-rom-ink font-medium">{{ row.shift.kind }} · {{ group.groupName }}</span>
                            <span class="text-muted-foreground text-sm">{{ formatShortDay(row.shift.startsAt) }} · {{ timeRange(row.shift) }}</span>
                        </div>
                        <span class="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <PhLock class="size-4" />
                            Closed — ask a {{ group.groupName }} officer
                        </span>
                    </CardContent>
                </Card>
            </section>

            <div v-if="empty" class="text-muted-foreground border-input rounded-md border border-dashed p-6 text-sm">
                Nothing to fill in. Every shift you have worked has a number.
            </div>

            <!-- 4. Everything already done, out of the way but not gone. -->
            <Collapsible v-if="done.length" class="group/done border-border rounded-md border">
                <CollapsibleTrigger class="text-muted-foreground hover:text-rom-ink flex w-full items-center justify-between gap-2 px-3 py-2 text-sm">
                    <span>{{ done.length }} recorded</span>
                    <PhCaretDown class="size-4 transition-transform group-data-[state=open]/done:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <ul class="flex flex-col gap-1 px-3 pb-3 text-sm">
                        <li v-for="row in done" :key="row.signUp.id" class="flex flex-wrap items-center gap-2">
                            <PhCheckCircle class="text-rom-ink size-4" />
                            <span class="text-muted-foreground">{{ formatShortDay(row.shift.startsAt) }} · {{ row.shift.kind }}</span>
                            <span class="text-rom-ink tabular-nums">{{ summary(row.signUp) }}</span>
                        </li>
                    </ul>
                </CollapsibleContent>
            </Collapsible>

            <p v-if="viewer.role === 'officer'" class="text-muted-foreground border-input border border-dashed p-3 text-xs">
                Officer view is identical — this page only ever shows your own shifts (ADR-0022 §4 draws the same line for hours). Correcting someone
                else's number happens somewhere else.
            </p>
        </template>
    </div>
</template>
