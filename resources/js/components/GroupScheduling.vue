<script setup lang="ts">
// Group Scheduling tab (#353, #354, PRD #352, ADR-0021 §1) — the Schedule read surface
// with the Scheduler's inline authoring on top. The section is org-open (deliberately
// not Meetings' members-only gate) and renders whenever the Group runs scheduling.
//
// Two states, resolved server-side: `open` is a single Schedule shown directly (the
// one current published Schedule, or a Schedule reached by permalink), and `schedules`
// is the list shown otherwise — current & upcoming first, then past — with an honest
// empty state when there are none. Drafts appear only for the Group's schedule admins;
// the server has already filtered the list to the viewer's audience.
//
// Authoring (a Scheduler / Chair / super-tier) is gated entirely by the server's `can`
// hints: a "New schedule" control, and per-Schedule edit / publish-or-unpublish /
// delete. Publishing and un-publishing are `state` transitions on the edit path — one
// PATCH carrying the new `state`. Every mutation is enforced by the SchedulePolicy
// regardless of what renders. Names and descriptions are as-authored content
// (ADR-0004); everything else is translated chrome.
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { groupShiftsByDay } from '@/scheduling/agenda';
import { type ScheduleDetail, type ScheduleListItem, type Scheduling, type SharedData, type ShiftAgendaItem } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { PhArrowLeft, PhEye, PhEyeSlash, PhPencilSimple, PhPlus, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ scheduling: Scheduling; canCreate: boolean; groupSlug: string }>();

const page = usePage<SharedData>();

// Schedule ranges are dates, not instants — a month, not an o'clock — so they format
// on the plain calendar date the server sends (`YYYY-MM-DD`), read in the active
// locale. Parsed as local midnight to avoid a UTC-shift landing on the day before.
const formatDate = (date: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'long' }).format(new Date(`${date}T00:00:00`));

const dateRange = (starts: string, ends: string) => trans('group.scheduling_panel.date_range', { start: formatDate(starts), end: formatDate(ends) });

// --- Agenda (#355) — the opened Schedule's Shifts, grouped by day -------------

// Shift times are instants read on the org wall clock the server shares, so 10am is
// 10am at the museum wherever the reader sits (prior art: GroupMeetings). Day grouping
// runs on that same wall clock in a pure module the Calendar will share.
const timeZone = page.props.timezone;

const formatTime = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { timeStyle: 'short', timeZone }).format(new Date(iso));

// The opened Schedule's Shifts, bucketed into ascending days. Each day heads a block;
// within a day the server's start order is preserved.
const agenda = computed(() => (props.scheduling.open ? groupShiftsByDay(props.scheduling.open.shifts, timeZone) : []));

const timeRange = (starts: string, ends: string) =>
    trans('group.scheduling_panel.agenda.time_range', { start: formatTime(starts), end: formatTime(ends) });

// The day heading is a plain calendar date (already the org-wall-clock day), formatted
// like the range: parsed as local midnight so no zone shift lands it on the day before.
const formatDay = (date: string) =>
    new Intl.DateTimeFormat(page.props.locale, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date(`${date}T00:00:00`));

// The list arrives already ordered (current & upcoming first, then past). Splitting
// here only heads the two blocks; an empty block is dropped rather than left bare.
const sections = computed(() =>
    (['current', 'past'] as const)
        .map((key) => ({ key, schedules: props.scheduling.schedules.filter((s) => s.is_past === (key === 'past')) }))
        .filter((section) => section.schedules.length > 0),
);

// The link back to the list from an opened Schedule — the bare section URL, which
// re-runs the navigation branch. Localised by the section-tab machinery on the page.
const listHref = computed(() => `/groups/${props.groupSlug}/scheduling`);

// --- Authoring (#354) — gated by the server's per-Schedule `can` hints --------

// The open editor: 'create', or the id of the Schedule being edited, or null when
// closed. Drives the single create/edit dialog.
const mode = ref<'create' | number | null>(null);

const form = useForm<{ name: string; starts_on: string; ends_on: string; description: string }>({
    name: '',
    starts_on: '',
    ends_on: '',
    description: '',
});

// Shape the payload the server expects: an emptied description collapses to null so a
// blank field is absent, not stored as an empty string.
form.transform((data) => ({
    name: data.name,
    starts_on: data.starts_on,
    ends_on: data.ends_on,
    description: data.description || null,
}));

const dialogOpen = computed({
    get: () => mode.value !== null,
    set: (open: boolean) => {
        if (!open) close();
    },
});

const dialogTitle = computed(() => trans(mode.value === 'create' ? 'group.scheduling_panel.create_title' : 'group.scheduling_panel.edit_title'));

const openCreate = () => {
    form.reset();
    form.clearErrors();
    mode.value = 'create';
};

const openEdit = (schedule: ScheduleDetail | ScheduleListItem) => {
    form.name = schedule.name;
    form.starts_on = schedule.starts_on;
    form.ends_on = schedule.ends_on;
    // A list item carries no description; the detail does. Only the detail edit
    // pre-fills it, and an edit from the list still sends the fields it shows.
    form.description = 'description' in schedule ? (schedule.description ?? '') : '';
    form.clearErrors();
    mode.value = schedule.id;
};

const close = () => {
    mode.value = null;
    form.reset();
};

const submit = () => {
    const onSuccess = () => close();
    if (mode.value === 'create') {
        form.post(route('schedules.store', { group: props.groupSlug }), { preserveScroll: true, onSuccess });
    } else if (mode.value !== null) {
        form.patch(route('schedules.update', { schedule: mode.value }), { preserveScroll: true, onSuccess });
    }
};

// Publish / un-publish — a `state` transition on the same edit route, sent bare.
const setState = (schedule: ScheduleDetail | ScheduleListItem, state: 'draft' | 'published') => {
    router.patch(route('schedules.update', { schedule: schedule.id }), { state }, { preserveScroll: true });
};

const destroy = (schedule: ScheduleDetail | ScheduleListItem) => {
    if (window.confirm(trans('group.scheduling_panel.confirm_delete'))) {
        router.delete(route('schedules.destroy', { schedule: schedule.id }), { preserveScroll: true });
    }
};

// --- Taking and dropping a Shift (#357) — the two floors, audience, and one seat ---

// Take a free seat: the server re-checks both floors, the `audience`, capacity, and the
// one-seat rule (StoreSignUpRequest → SignUpPolicy). `can.signUp` gates the button, so it
// only shows where a Sign-up would take; the POST carries no body — the seat is the viewer.
const take = (shift: ShiftAgendaItem) => {
    router.post(route('sign-ups.store', { shift: shift.id }), {}, { preserveScroll: true });
};

// Drop the seat the viewer holds — one click from where they signed up. Cancel has no
// deadline (ADR-0021). `signup_id` is the viewer's own seat; nothing renders without it.
const drop = (shift: ShiftAgendaItem) => {
    if (shift.signup_id !== null) {
        router.delete(route('sign-ups.destroy', { signUp: shift.signup_id }), { preserveScroll: true });
    }
};

// The seated Members' display names ("First Last"), for the "who I'll be working with" line.
const signUpName = (signUp: ShiftAgendaItem['signups'][number]) => `${signUp.first_name} ${signUp.last_name}`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="canCreate && !scheduling.open" class="flex justify-end">
            <Button type="button" size="sm" class="gap-1.5" @click="openCreate">
                <PhPlus class="size-4" />
                {{ trans('group.scheduling_panel.new') }}
            </Button>
        </div>

        <!-- One Schedule, opened directly (the single current published one, or a
             permalink): its header card, then the Agenda's day-grouped Shifts. -->
        <template v-if="scheduling.open">
            <TextLink :href="listHref" class="inline-flex items-center gap-1.5 text-sm">
                <PhArrowLeft class="size-4 shrink-0" />
                {{ trans('group.scheduling_panel.back_to_list') }}
            </TextLink>

            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex flex-col gap-1">
                            <CardTitle class="text-rom-ink flex flex-wrap items-center gap-2 text-lg">
                                {{ scheduling.open.name }}
                                <Badge v-if="scheduling.open.state === 'draft'" variant="secondary">
                                    {{ trans('group.scheduling_panel.draft_badge') }}
                                </Badge>
                            </CardTitle>
                            <p class="text-muted-foreground text-sm">{{ dateRange(scheduling.open.starts_on, scheduling.open.ends_on) }}</p>
                        </div>
                        <div v-if="scheduling.open.can.update || scheduling.open.can.delete" class="flex shrink-0 flex-wrap gap-1">
                            <Button
                                v-if="scheduling.open.can.update"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="gap-1.5"
                                @click="openEdit(scheduling.open)"
                            >
                                <PhPencilSimple class="size-4" />
                                {{ trans('group.scheduling_panel.edit') }}
                            </Button>
                            <Button
                                v-if="scheduling.open.state === 'draft' && scheduling.open.can.publish"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="gap-1.5"
                                @click="setState(scheduling.open, 'published')"
                            >
                                <PhEye class="size-4" />
                                {{ trans('group.scheduling_panel.publish') }}
                            </Button>
                            <Button
                                v-if="scheduling.open.state === 'published' && scheduling.open.can.unpublish"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="gap-1.5"
                                @click="setState(scheduling.open, 'draft')"
                            >
                                <PhEyeSlash class="size-4" />
                                {{ trans('group.scheduling_panel.unpublish') }}
                            </Button>
                            <Button
                                v-if="scheduling.open.can.delete"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="gap-1.5"
                                @click="destroy(scheduling.open)"
                            >
                                <PhTrash class="size-4" />
                                {{ trans('group.scheduling_panel.delete') }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="scheduling.open.description">
                    <p class="text-rom-ink text-base whitespace-pre-line">{{ scheduling.open.description }}</p>
                </CardContent>
            </Card>

            <!-- Agenda (#355) — the Schedule's Shifts, grouped by day on the org wall
                 clock. Reads the same at 3 days or 30; each Shift shows its time range,
                 kind (where the Group uses kinds), and how many of its seats are taken. -->
            <section v-if="agenda.length" class="flex flex-col gap-4" :aria-label="trans('group.scheduling_panel.agenda.aria_label')">
                <div v-for="day in agenda" :key="day.date" class="flex flex-col gap-2">
                    <h3 class="text-muted-foreground text-sm font-medium tracking-wide uppercase">{{ formatDay(day.date) }}</h3>
                    <Card v-for="shift in day.shifts" :key="shift.id">
                        <CardContent class="flex flex-col gap-2 py-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="text-rom-ink font-medium">{{ timeRange(shift.starts_at, shift.ends_at) }}</span>
                                    <span v-if="shift.kind" class="text-muted-foreground text-sm">{{ shift.kind }}</span>
                                </div>
                                <span class="text-muted-foreground text-sm tabular-nums">
                                    {{
                                        trans('group.scheduling_panel.agenda.seats', { taken: String(shift.taken), capacity: String(shift.capacity) })
                                    }}
                                </span>
                            </div>

                            <!-- Who is on the floor (#357) — visible to every reader who can read
                                 the Schedule, non-members included. An honest empty line otherwise. -->
                            <p v-if="shift.signups.length" class="text-muted-foreground text-sm">
                                <span class="font-medium">{{ trans('group.scheduling_panel.agenda.sign_up.signed_up_label') }}:</span>
                                {{ shift.signups.map(signUpName).join(', ') }}
                            </p>
                            <p v-else class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.agenda.sign_up.nobody') }}</p>

                            <!-- Take / drop, from the same place. `signup_id` means "I hold a seat";
                                 `can.signUp` means "a free seat is offered to me". A full Shift the
                                 viewer has no seat on shows as full with neither button. -->
                            <div class="flex items-center gap-2">
                                <Button v-if="shift.signup_id !== null" type="button" variant="outline" size="sm" @click="drop(shift)">
                                    {{ trans('group.scheduling_panel.agenda.sign_up.drop') }}
                                </Button>
                                <Button v-else-if="shift.can.signUp" type="button" size="sm" @click="take(shift)">
                                    {{ trans('group.scheduling_panel.agenda.sign_up.take') }}
                                </Button>
                                <span v-else-if="shift.taken >= shift.capacity" class="text-muted-foreground text-sm font-medium">
                                    {{ trans('group.scheduling_panel.agenda.sign_up.full') }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Honest empty state — the Schedule is published but holds no Shifts yet. -->
            <p v-else class="text-muted-foreground py-8 text-center text-sm">{{ trans('group.scheduling_panel.agenda.empty') }}</p>
        </template>

        <!-- The list — current & upcoming first, then past, each block headed. -->
        <template v-else-if="scheduling.schedules.length">
            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h3 class="text-muted-foreground text-sm font-medium tracking-wide uppercase">
                    {{ trans(`group.scheduling_panel.${section.key}_heading`) }}
                </h3>

                <Card v-for="schedule in section.schedules" :key="schedule.id">
                    <CardHeader>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex flex-col gap-1">
                                <CardTitle class="flex flex-wrap items-center gap-2 text-lg">
                                    <TextLink :href="schedule.url" class="text-rom-ink font-semibold">{{ schedule.name }}</TextLink>
                                    <Badge v-if="schedule.state === 'draft'" variant="secondary">
                                        {{ trans('group.scheduling_panel.draft_badge') }}
                                    </Badge>
                                </CardTitle>
                                <p class="text-muted-foreground text-sm">{{ dateRange(schedule.starts_on, schedule.ends_on) }}</p>
                            </div>
                            <div v-if="schedule.can.update || schedule.can.delete" class="flex shrink-0 flex-wrap gap-1">
                                <Button
                                    v-if="schedule.can.update"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-1.5"
                                    @click="openEdit(schedule)"
                                >
                                    <PhPencilSimple class="size-4" />
                                    {{ trans('group.scheduling_panel.edit') }}
                                </Button>
                                <Button
                                    v-if="schedule.state === 'draft' && schedule.can.publish"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-1.5"
                                    @click="setState(schedule, 'published')"
                                >
                                    <PhEye class="size-4" />
                                    {{ trans('group.scheduling_panel.publish') }}
                                </Button>
                                <Button
                                    v-if="schedule.state === 'published' && schedule.can.unpublish"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-1.5"
                                    @click="setState(schedule, 'draft')"
                                >
                                    <PhEyeSlash class="size-4" />
                                    {{ trans('group.scheduling_panel.unpublish') }}
                                </Button>
                                <Button v-if="schedule.can.delete" type="button" variant="ghost" size="sm" class="gap-1.5" @click="destroy(schedule)">
                                    <PhTrash class="size-4" />
                                    {{ trans('group.scheduling_panel.delete') }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            </section>
        </template>

        <!-- Honest empty state — the Group runs scheduling but has no Schedules yet. -->
        <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.scheduling_panel.empty') }}</p>

        <!-- Authoring create/edit dialog (#354) — one form, reused; opened by the
             "New schedule" control or a per-Schedule edit. -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="schedule-name">{{ trans('group.scheduling_panel.field.name') }}</Label>
                        <Input id="schedule-name" v-model="form.name" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="schedule-starts-on">{{ trans('group.scheduling_panel.field.starts_on') }}</Label>
                        <Input id="schedule-starts-on" v-model="form.starts_on" type="date" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="schedule-ends-on">{{ trans('group.scheduling_panel.field.ends_on') }}</Label>
                        <Input id="schedule-ends-on" v-model="form.ends_on" type="date" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="schedule-description">{{ trans('group.scheduling_panel.field.description') }}</Label>
                        <Textarea id="schedule-description" v-model="form.description" :rows="4" />
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('group.scheduling_panel.save') }}</Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="close">
                            {{ trans('group.scheduling_panel.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
