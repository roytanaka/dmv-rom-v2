<script setup lang="ts">
// Group Scheduling tab (#353, #354, PRD #352, ADR-0021 §1) — the Schedule read surface
// with the Scheduler's inline authoring on top. The section is org-open (deliberately
// not Meetings' members-only gate) and renders whenever the Group runs scheduling.
//
// Two states, resolved server-side: `open` is the Schedule a permalink addressed, and
// `schedules` is the list every other visit gets — current & upcoming first, then past —
// with an honest empty state when there are none. The section always lists first, like
// every other section tab. Drafts appear only for the Group's schedule admins; the
// server has already filtered the list to the viewer's audience.
//
// Authoring (a Scheduler / Chair / super-tier) is gated entirely by the server's `can`
// hints: a "New schedule" control, and per-Schedule edit / publish-or-unpublish /
// delete. Publishing and un-publishing are `state` transitions on the edit path — one
// PATCH carrying the new `state`. Every mutation is enforced by the SchedulePolicy
// regardless of what renders. Names and descriptions are as-authored content
// (ADR-0004); everything else is translated chrome.
import ForeignShiftBand from '@/components/ForeignShiftBand.vue';
import ScheduleCalendar from '@/components/ScheduleCalendar.vue';
import ShiftCard from '@/components/ShiftCard.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { buildAgenda } from '@/scheduling/agenda';
import { type ScheduleDetail, type ScheduleListItem, type Scheduling, type SharedData, type ShiftAgendaItem } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { PhArrowLeft, PhBinoculars, PhCalendarBlank, PhEye, PhEyeSlash, PhListBullets, PhPencilSimple, PhPlus, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';

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

// The opened Schedule's own Shifts and the foreign open Shifts other Groups advertise,
// partitioned into ascending days by the shared pure module (#361). Each day heads a block;
// own Shifts stay in `shifts`, foreign ones in attributed `bands`, never interleaved. Both
// views read this same grouping.
const agenda = computed(() => (props.scheduling.open ? buildAgenda(props.scheduling.open.shifts, props.scheduling.open.foreign, timeZone) : []));

// The own Shifts as plain day groups — the Calendar's month grid lays these out; its foreign
// chips read the foreign day groups. Both are derived from the one merged agenda so the two
// views can never disagree on which day a Shift lands.
const ownAgenda = computed(() => agenda.value.map((day) => ({ date: day.date, shifts: day.shifts })));
const foreignAgenda = computed(() => agenda.value.map((day) => ({ date: day.date, shifts: day.bands.flatMap((band) => band.shifts) })));

// The view toggle and Calendar are about the Schedule's own month; the empty state speaks to
// its own emptiness. Foreign discovery is offered on top, only when there is something to find.
const hasForeign = computed(() => (props.scheduling.open?.foreign.length ?? 0) > 0);

// The master open/close-all beside the view controls (#361). Foreign discovery is always
// *present* — the bands show collapsed to one line — but starts closed: expanding all bands in
// Agenda, and turning on the Calendar's foreign chips. Per-band toggles still work after.
const foreignExpanded = ref(false);

// The day heading is a plain calendar date (already the org-wall-clock day), formatted
// like the range: parsed as local midnight so no zone shift lands it on the day before.
const formatDay = (date: string) =>
    new Intl.DateTimeFormat(page.props.locale, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date(`${date}T00:00:00`));

// --- View preference (#360, ADR-0021 §7) — the reader chooses, not the Scheduler ------

// Agenda or Calendar. The viewer picks and the choice is remembered client-side only —
// no member column, no Schedule column, no route, no server state (ADR-0021 §7). Agenda is
// what a reader gets before choosing: the ref starts there and is hydrated from
// localStorage on mount, so SSR and the first paint are always the Agenda. `localStorage`
// is touched only inside `onMounted`/`watch`, which never run during SSR.
const VIEW_KEY = 'dmv.scheduling.view';
const view = ref<'agenda' | 'calendar'>('agenda');

onMounted(() => {
    const saved = localStorage.getItem(VIEW_KEY);
    if (saved === 'agenda' || saved === 'calendar') view.value = saved;
});

watch(view, (value) => localStorage.setItem(VIEW_KEY, value));

// The list arrives already ordered (current & upcoming first, then past). Splitting
// here only heads the two blocks; an empty block is dropped rather than left bare.
const sections = computed(() =>
    (['current', 'past'] as const)
        .map((key) => ({ key, schedules: props.scheduling.schedules.filter((s) => s.is_past === (key === 'past')) }))
        .filter((section) => section.schedules.length > 0),
);

// The link back to the list from a Schedule opened by permalink — the bare section
// URL, which always lists. Built through the route helper so the French twin comes
// out right, as the Roster's show-past toggle already does.
const listHref = computed(() => route('groups.show', { group: props.groupSlug, section: 'scheduling' }));

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

// --- Officer assignment and removal (#359) — the Scheduler seats and clears a named Member ---

// The Shift being assigned to, driving the picker dialog; null when closed. The picker's
// roster is the Group's placeable Members (server-filtered to both floors); the server
// re-checks the schedule-admin gate, both floors, and capacity on POST regardless.
const assigningShift = ref<ShiftAgendaItem | null>(null);
const assignFilter = ref('');

const assignOpen = computed({
    get: () => assigningShift.value !== null,
    set: (open: boolean) => {
        if (!open) assigningShift.value = null;
    },
});

// The roster narrows to a name match as the Scheduler types — a Reception desk has a
// handful of regulars, but a Friends Committee's roster is longer.
const filteredRoster = computed(() => {
    const needle = assignFilter.value.trim().toLowerCase();
    const roster = props.scheduling.roster;

    if (!needle) return roster;

    return roster.filter((candidate) => `${candidate.first_name} ${candidate.last_name}`.toLowerCase().includes(needle));
});

const openAssign = (shift: ShiftAgendaItem) => {
    assignFilter.value = '';
    assigningShift.value = shift;
};

// Place the chosen Member on the open Shift. The picker carries the `member_id`; the server
// authorises (schedule-admin gate) and validates (both floors, capacity, one seat) on POST.
const assign = (candidateId: number) => {
    if (assigningShift.value === null) return;

    router.post(
        route('assignments.store', { shift: assigningShift.value.id }),
        { member_id: candidateId },
        {
            preserveScroll: true,
            onSuccess: () => {
                assigningShift.value = null;
            },
        },
    );
};

// Remove a seat the Scheduler administers — officer removal, so a placed regular who stops
// coming is not stranded. Reuses the drop seam, whose ownership-gated email keeps this
// silent. A confirm guards it: unlike a self-drop, this clears someone else.
const removeSeat = (signUpId: number) => {
    if (window.confirm(trans('group.scheduling_panel.agenda.assign.confirm_remove'))) {
        router.delete(route('sign-ups.destroy', { signUp: signUpId }), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="canCreate && !scheduling.open" class="flex justify-end">
            <Button type="button" size="sm" class="gap-1.5" @click="openCreate">
                <PhPlus class="size-4" />
                {{ trans('group.scheduling_panel.new') }}
            </Button>
        </div>

        <!-- One Schedule, addressed by permalink: its header card, then the Agenda's
             day-grouped Shifts. -->
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

            <!-- View toggle (#360) — the reader chooses Agenda or Calendar; the Scheduler
                 has no equivalent control in the authoring form. Presentation only: it hits
                 no route and only shows once there are Shifts to lay out either way. -->
            <div v-if="agenda.length" class="flex flex-wrap items-center justify-between gap-2">
                <!-- Master open/close-all for foreign open Shifts (#361) — expands every band in
                     Agenda, turns the Calendar's foreign chips on. Shown only when there is
                     something to find, so the control never promises an empty discovery. -->
                <Button
                    v-if="hasForeign"
                    type="button"
                    size="sm"
                    :variant="foreignExpanded ? 'default' : 'outline'"
                    class="gap-1.5"
                    :aria-pressed="foreignExpanded"
                    @click="foreignExpanded = !foreignExpanded"
                >
                    <PhBinoculars class="size-4" />
                    {{ trans(foreignExpanded ? 'group.scheduling_panel.foreign.hide_all' : 'group.scheduling_panel.foreign.show_all') }}
                </Button>
                <span v-else></span>

                <div class="bg-muted inline-flex rounded-md p-0.5" role="group" :aria-label="trans('group.scheduling_panel.view.aria_label')">
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'agenda' ? 'default' : 'ghost'"
                        class="gap-1.5"
                        :aria-pressed="view === 'agenda'"
                        @click="view = 'agenda'"
                    >
                        <PhListBullets class="size-4" />
                        {{ trans('group.scheduling_panel.view.agenda') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'calendar' ? 'default' : 'ghost'"
                        class="gap-1.5"
                        :aria-pressed="view === 'calendar'"
                        @click="view = 'calendar'"
                    >
                        <PhCalendarBlank class="size-4" />
                        {{ trans('group.scheduling_panel.view.calendar') }}
                    </Button>
                </div>
            </div>

            <!-- Agenda (#355) — the Schedule's Shifts, grouped by day on the org wall clock.
                 Reads the same at 3 days or 30. Each Shift is the shared ShiftCard, so the
                 Calendar's day sheet shows the same facts and affordances. -->
            <section
                v-if="agenda.length && view === 'agenda'"
                class="flex flex-col gap-4"
                :aria-label="trans('group.scheduling_panel.agenda.aria_label')"
            >
                <div v-for="day in agenda" :key="day.date" class="flex flex-col gap-2">
                    <h3 class="text-muted-foreground text-sm font-medium tracking-wide uppercase">{{ formatDay(day.date) }}</h3>
                    <ShiftCard
                        v-for="shift in day.shifts"
                        :key="shift.id"
                        :shift="shift"
                        @take="take"
                        @drop="drop"
                        @assign="openAssign"
                        @remove="removeSeat"
                    />
                    <!-- Foreign open Shifts other Groups advertise (#361) — always present but
                         collapsed to one line, banded and attributed by owning Group, kept apart
                         from this Group's own Shifts above. -->
                    <ForeignShiftBand
                        v-for="band in day.bands"
                        :key="`${day.date}-${band.group}`"
                        :band="band"
                        :expanded="foreignExpanded"
                        @take="take"
                        @drop="drop"
                    />
                </div>
            </section>

            <!-- Calendar (#360) — the same Shifts laid out as a month grid; a day opens a
                 sheet of the same ShiftCards. -->
            <ScheduleCalendar
                v-else-if="agenda.length && view === 'calendar'"
                :agenda="ownAgenda"
                :foreign="foreignAgenda"
                :foreign-expanded="foreignExpanded"
                :starts-on="scheduling.open.starts_on"
                :ends-on="scheduling.open.ends_on"
                @take="take"
                @drop="drop"
                @assign="openAssign"
                @remove="removeSeat"
            />

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

        <!-- Officer assignment picker (#359) — opened from a Shift's "Place a member"
             control. Draws from the Group's placeable roster (server-filtered to both
             floors); the server re-checks the gate, floors, and capacity on POST. -->
        <Dialog v-model:open="assignOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('group.scheduling_panel.agenda.assign.title') }}</DialogTitle>
                </DialogHeader>
                <div class="flex flex-col gap-3">
                    <Input v-model="assignFilter" :placeholder="trans('group.scheduling_panel.agenda.assign.search')" />
                    <div class="flex max-h-72 flex-col gap-0.5 overflow-y-auto">
                        <Button
                            v-for="candidate in filteredRoster"
                            :key="candidate.id"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="justify-start"
                            @click="assign(candidate.id)"
                        >
                            {{ candidate.first_name }} {{ candidate.last_name }}
                        </Button>
                        <p v-if="!filteredRoster.length" class="text-muted-foreground p-2 text-sm">
                            {{ trans('group.scheduling_panel.agenda.assign.empty') }}
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
