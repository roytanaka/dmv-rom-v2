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
import InputError from '@/components/InputError.vue';
import ScheduleCalendar from '@/components/ScheduleCalendar.vue';
import ShiftCard from '@/components/ShiftCard.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import EmailMenu from '@/emailing/EmailMenu.vue';
import { type Recipient } from '@/emailing/composer';
import { buildAgenda } from '@/scheduling/agenda';
import { type ScheduleDetail, type ScheduleListItem, type Scheduling, type SharedData, type ShiftAgendaItem, type VisitorProvenance } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    PhArrowLeft,
    PhBinoculars,
    PhCalendarBlank,
    PhEye,
    PhEyeSlash,
    PhListBullets,
    PhPencilSimple,
    PhPlus,
    PhStack,
    PhTrash,
    PhUserPlus,
    PhX,
} from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    scheduling: Scheduling;
    canCreate: boolean;
    collectsVisitorCount: boolean;
    collectsExtraInteractions: boolean;
    collectsVisitorProvenance: boolean;
    reminders: { enabled: boolean; leadDays: number };
    canManageReminders: boolean;
    emptyDesk: { enabled: boolean; daysAhead: number; shiftKinds: { id: number; name: string; watched: boolean }[] };
    canManageEmptyDesk: boolean;
    groupSlug: string;
    groupName: string;
}>();

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

// --- My sign-ups (#449, ADR-0023 §5) — the outstanding-shifts panel ------------

// "My Sign-ups on this Group": the viewer's upcoming Shifts, plus any past Shift inside the
// 28-day window still owed a number. Server-resolved (`scheduling.mine`), already the viewer's
// own seats and no one else's, ordered by start, and crossing Schedules — the one surface that
// reaches a three-week-old Shift on last month's Schedule, a different page. The panel is
// **absent, not empty**, when there is nothing to show: an empty list renders no panel at all,
// and a Group that collects no count sends an empty list too. Each entry is a ShiftCard, so
// filing a number here rides the same PATCH seam as the Agenda; a past Shift shows the sign-out
// form (its window has no upper bound), an upcoming one just lists with its drop control.
const mine = computed(() => props.scheduling.mine);

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

// The opened Schedule's Email control (#490, ADR-0024 §6.3): its menu leads with "Sign-ups
// on <Schedule>", then the owning Group's Audiences, all resolved server-side. The hand-pick
// pool is the Group's placeable roster the server already sent for officer assignment (empty
// for a plain reader, who cannot hand-pick here anyway), each row shaped to a Recipient.
const emailRoster = computed<Recipient[]>(() =>
    props.scheduling.roster.map((candidate) => ({
        id: candidate.id,
        first_name: candidate.first_name,
        last_name: candidate.last_name,
        photo: candidate.photo,
        standing: candidate.standing,
    })),
);

// --- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and lead
// days, gated by `canManageReminders`. One PATCH to the dedicated endpoint; the server
// re-checks the gate. The form seeds from the Group's current settings.
const reminderForm = useForm<{ reminders_enabled: boolean; reminder_lead_days: number }>({
    reminders_enabled: props.reminders.enabled,
    reminder_lead_days: props.reminders.leadDays,
});

const saveReminders = () => reminderForm.patch(route('groups.reminders.update', { group: props.groupSlug }), { preserveScroll: true });

// --- Empty-desk settings (#487, ADR-0024 §7) — the schedule-admin's on/off switch, look-ahead,
// and the tick-rows marking which shift kinds to watch, gated by `canManageEmptyDesk`. One PATCH
// to the dedicated endpoint; the server re-checks the gate. The form seeds from the Group's
// current settings, its watched-kinds set drawn from the kinds already flagged.
const emptyDeskForm = useForm<{ empty_desk_alert_enabled: boolean; empty_desk_days_ahead: number; watched_shift_kinds: number[] }>({
    empty_desk_alert_enabled: props.emptyDesk.enabled,
    empty_desk_days_ahead: props.emptyDesk.daysAhead,
    watched_shift_kinds: props.emptyDesk.shiftKinds.filter((kind) => kind.watched).map((kind) => kind.id),
});

const toggleWatchedKind = (id: number, on: boolean) => {
    emptyDeskForm.watched_shift_kinds = on
        ? [...emptyDeskForm.watched_shift_kinds, id]
        : emptyDeskForm.watched_shift_kinds.filter((kindId) => kindId !== id);
};

const saveEmptyDesk = () => emptyDeskForm.patch(route('groups.empty-desk.update', { group: props.groupSlug }), { preserveScroll: true });

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

// --- Recording the numbers (#445, #450, ADR-0023 §5) — one seam, three surfaces ---

// File the numbers on the named Sign-up: the viewer's own seat from the sign-out panel or the
// outstanding list (#445), or any seat an Officer corrects with the pencil (#450). `signUpId` is
// that seat — the card resolves it — and the write always names it in the route, never the body.
// The server re-checks the policy (the seat-holder inside the window, or a schedule admin with no
// deadline) and the whole rule (required count, optional extra, both whole and non-negative, GDR's
// five origins summing to the count) on PATCH. The extra-interaction field rides only where the
// Group collects the split — a count-only Group refuses it server-side, so it is never sent there —
// and carries null when its box is blank, distinct from a recorded zero (#447, ADR-0023 §2). GDR's
// five origins ride only where the Group collects provenance (#448, ADR-0023 §3); everywhere else
// the server refuses them, so they are never sent.
const record = (payload: { signUpId: number; count: number; extra: number | null; provenance: VisitorProvenance | null }) => {
    const body: { visitor_count: number; extra_interaction_count?: number | null } & Partial<VisitorProvenance> = {
        visitor_count: payload.count,
    };

    if (props.collectsExtraInteractions) {
        body.extra_interaction_count = payload.extra;
    }

    if (props.collectsVisitorProvenance && payload.provenance !== null) {
        Object.assign(body, payload.provenance);
    }

    router.patch(route('sign-ups.record', { signUp: payload.signUpId }), body, { preserveScroll: true });
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

// --- Shift authoring (#356 front end, PRD #352, ADR-0021 §2) — add / edit / delete ---

// The Scheduler's inline authoring on an opened Schedule, mirroring Schedule authoring above
// and gated the same way: the "New shift" control and each Shift's edit / delete answer to the
// server's `can` hints (the schedule-admin gate; `can.delete` folds in the zero-Sign-ups rule).
// Every mutation is re-checked by the Shift Form Requests regardless of what renders.

// The two audience cases a Shift can carry (ShiftAudience) — the discovery filter the picker
// offers, labelled from the lang file. `group` is the default; `open` invites the whole org.
const AUDIENCES = ['group', 'open'] as const;

// The granularity every Shift time is entered at, in seconds. Shifts are scheduled to the
// five minutes, never to the minute, so the native picker steps in fives rather than making
// the Scheduler scroll sixty entries to reach half past. Browsers also validate against it,
// so a time off the grid is rejected before it reaches the form.
const TIME_STEP_SECONDS = 300;

// The native-select styling, matching the Roster's pickers (no shadcn Select in the repo yet).
const SELECT_CLASS =
    'border-input bg-background focus-visible:border-rom-slate focus-visible:ring-rom-slate-50 flex h-11 w-full rounded-none border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden';

// A Shift's times are instants on the org wall clock, like a Meeting's. A datetime-local input
// has no zone of its own, so pre-fill renders the UTC instant on the org wall clock and the
// server reads what it sends back as org-local (App\Support\OrgTime) — saving an unedited Shift
// is a no-op. (Prior art: GroupMeetings' held_at.)
const toDateTimeLocal = (iso: string) => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(iso));
    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';
    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
};

// The open Shift editor: 'create', the id of the Shift being edited, or null when closed.
const shiftMode = ref<'create' | number | null>(null);

const shiftForm = useForm<{ starts_at: string; ends_at: string; capacity: number; shift_kind_id: number | null; audience: string }>({
    starts_at: '',
    ends_at: '',
    capacity: 1,
    shift_kind_id: null,
    audience: 'group',
});

const shiftDialogOpen = computed({
    get: () => shiftMode.value !== null,
    set: (open: boolean) => {
        if (!open) closeShift();
    },
});

const shiftDialogTitle = computed(() =>
    trans(shiftMode.value === 'create' ? 'group.scheduling_panel.create_shift_title' : 'group.scheduling_panel.edit_shift_title'),
);

const openShiftCreate = () => {
    shiftForm.reset();
    shiftForm.clearErrors();
    shiftMode.value = 'create';
};

const openShiftEdit = (shift: ShiftAgendaItem) => {
    shiftForm.starts_at = toDateTimeLocal(shift.starts_at);
    shiftForm.ends_at = toDateTimeLocal(shift.ends_at);
    shiftForm.capacity = shift.capacity;
    shiftForm.shift_kind_id = shift.shift_kind_id;
    shiftForm.audience = shift.audience;
    shiftForm.clearErrors();
    shiftMode.value = shift.id;
};

const closeShift = () => {
    shiftMode.value = null;
    shiftForm.reset();
};

const submitShift = () => {
    const onSuccess = () => closeShift();
    if (shiftMode.value === 'create') {
        if (props.scheduling.open === null) return;
        shiftForm.post(route('shifts.store', { schedule: props.scheduling.open.id }), { preserveScroll: true, onSuccess });
    } else if (shiftMode.value !== null) {
        shiftForm.patch(route('shifts.update', { shift: shiftMode.value }), { preserveScroll: true, onSuccess });
    }
};

// Delete confirms before firing, matching the Schedule delete's shape. The button only
// renders where `can.delete` holds — a schedule admin, and the Shift at zero Sign-ups.
const destroyShift = (shift: ShiftAgendaItem) => {
    if (window.confirm(trans('group.scheduling_panel.confirm_delete_shift'))) {
        router.delete(route('shifts.destroy', { shift: shift.id }), { preserveScroll: true });
    }
};

// --- Bulk-create / bulk-delete Shifts (#362 front end, PRD #352, ADR-0021 §2) ---

// The labour-saver that makes a month one form run: one Shift on every chosen weekday
// across a date range, and its symmetric undo on the same filter. It adds no interval and
// no stored pattern — the weekdays and range live only in this form (ADR-0021 §2). Gated by
// the same schedule-admin verdict as single-Shift authoring (`can.update`).

// The seven weekdays, labelled in the active locale. Index is the Carbon day number
// (0 = Sunday … 6 = Saturday) the server fans out on; a fixed reference week formatted in
// UTC keeps each label on its own day regardless of the viewer's device zone. The stamped
// wall-clock times are read org-local server-side, matching the Agenda's day grouping.
const WEEKDAYS = computed(() =>
    Array.from({ length: 7 }, (_, index) => ({
        value: index,
        label: new Intl.DateTimeFormat(page.props.locale, { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2023, 0, 1 + index))),
    })),
);

const bulkOpen = ref(false);

const bulkForm = useForm<{
    starts_time: string;
    ends_time: string;
    capacity: number;
    shift_kind_id: number | null;
    days_of_week: number[];
    from_date: string;
    to_date: string;
}>({
    starts_time: '',
    ends_time: '',
    capacity: 1,
    shift_kind_id: null,
    days_of_week: [],
    from_date: '',
    to_date: '',
});

const openBulk = () => {
    bulkForm.reset();
    bulkForm.clearErrors();
    // Seed the range from the opened Schedule so a full-month run is a few clicks, not typing.
    if (props.scheduling.open) {
        bulkForm.from_date = props.scheduling.open.starts_on;
        bulkForm.to_date = props.scheduling.open.ends_on;
    }
    bulkOpen.value = true;
};

const bulkDialogOpen = computed({
    get: () => bulkOpen.value,
    set: (open: boolean) => {
        if (!open) closeBulk();
    },
});

const closeBulk = () => {
    bulkOpen.value = false;
    bulkForm.reset();
};

// The run report rides back in the shared `flash` prop (HandleInertiaRequests). It is
// output, not an error — so it renders on the page, dismissable, until the next run or a
// manual dismiss. A fresh run clears the dismissal so its own report shows.
const reportDismissed = ref(false);
const shiftsReport = computed(() => (reportDismissed.value ? null : (page.props.flash?.shiftsBulk ?? null)));

// Both actions post the same filter; only the verb differs. On success the dialog closes,
// the dismissal resets so the fresh report shows, and the run's own errors surface per field.
const runBulk = (action: 'create' | 'delete') => {
    const onSuccess = () => {
        reportDismissed.value = false;
        closeBulk();
    };
    if (props.scheduling.open === null) return;
    const schedule = props.scheduling.open.id;

    if (action === 'create') {
        bulkForm.post(route('shifts.bulk-store', { schedule }), { preserveScroll: true, onSuccess });
    } else if (window.confirm(trans('group.scheduling_panel.bulk.confirm_delete'))) {
        // Bulk-delete removes many rows at once, so it confirms first. It carries the same
        // filter the create form holds (delete via useForm sends the form's fields).
        bulkForm.delete(route('shifts.bulk-destroy', { schedule }), { preserveScroll: true, onSuccess });
    }
};

// --- Bulk-place / bulk-remove a Member's Sign-ups (#363 front end, PRD #352, ADR-0021 §5) ---

// The Member-in-Schedule labour-saver that retires Reception's fortnight: one regular placed
// across every Shift a filter names — weekly or biweekly — in a single run, and unwound the
// same way. Deliberately distinct from bulk-creating Shifts (#362): a different actor's
// different moment, so it is its own entry point and form. The interval and its anchor live
// only in this form; no pattern is stored (ADR-0021 §5). Gated by the same schedule-admin
// verdict (`can.update`) that reveals the placeable roster; the server re-checks the gate,
// both sign-up floors, capacity and the one-seat rule on write regardless.

// The two intervals the form offers — every week, or alternating weeks phased from an anchor.
const INTERVALS = ['weekly', 'biweekly'] as const;

const bulkAssignOpen = ref(false);

const bulkAssignForm = useForm<{
    member_id: number | null;
    starts_time: string;
    ends_time: string;
    days_of_week: number[];
    from_date: string;
    to_date: string;
    interval: 'weekly' | 'biweekly';
    anchor_date: string;
}>({
    member_id: null,
    starts_time: '',
    ends_time: '',
    days_of_week: [],
    from_date: '',
    to_date: '',
    interval: 'weekly',
    anchor_date: '',
});

const openBulkAssign = () => {
    bulkAssignForm.reset();
    bulkAssignForm.clearErrors();
    // Seed the range and the biweekly anchor from the opened Schedule so a full run is a few
    // clicks, not typing; the anchor defaults to the range start and only matters biweekly.
    if (props.scheduling.open) {
        bulkAssignForm.from_date = props.scheduling.open.starts_on;
        bulkAssignForm.to_date = props.scheduling.open.ends_on;
        bulkAssignForm.anchor_date = props.scheduling.open.starts_on;
    }
    bulkAssignOpen.value = true;
};

const bulkAssignDialogOpen = computed({
    get: () => bulkAssignOpen.value,
    set: (open: boolean) => {
        if (!open) closeBulkAssign();
    },
});

const closeBulkAssign = () => {
    bulkAssignOpen.value = false;
    bulkAssignForm.reset();
};

// The run report rides back in the shared `flash` prop, like the bulk-Shift report — output,
// not an error, so it renders on the page, dismissable, until the next run or a manual
// dismiss. Kept apart from the Shift report: distinct counts (seats filled / cleared) and its
// own flash key, so a placement run and a Shift run never overwrite each other's report.
const assignReportDismissed = ref(false);
const assignmentsReport = computed(() => (assignReportDismissed.value ? null : (page.props.flash?.assignmentsBulk ?? null)));

// Both actions post the same filter; only the verb differs. Remove confirms first — it clears
// someone else's seats, plural. On success the dialog closes, the dismissal resets so the
// fresh report shows, and the run's own errors surface per field.
const runBulkAssign = (action: 'place' | 'remove') => {
    const onSuccess = () => {
        assignReportDismissed.value = false;
        closeBulkAssign();
    };
    if (props.scheduling.open === null) return;
    const schedule = props.scheduling.open.id;

    if (action === 'place') {
        bulkAssignForm.post(route('assignments.bulk-store', { schedule }), { preserveScroll: true, onSuccess });
    } else if (window.confirm(trans('group.scheduling_panel.bulk_assign.confirm_remove'))) {
        // Bulk-remove clears many seats at once — someone else's — so it confirms first. It
        // carries the same filter the place form holds (delete via useForm sends its fields).
        bulkAssignForm.delete(route('assignments.bulk-destroy', { schedule }), { preserveScroll: true, onSuccess });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- My sign-ups (#449, ADR-0023 §5) — the outstanding-shifts panel: the viewer's own
             upcoming Shifts and any past Shift still owed a number, crossing Schedules. Absent
             (not empty) when there is nothing to show, so it renders only when the list is
             non-empty. Each Shift is the shared ShiftCard, so a number is filed straight from
             here through the same seam as the Agenda. -->
        <section v-if="mine.length" class="flex flex-col gap-2" :aria-label="trans('group.scheduling_panel.mine.aria_label')">
            <h3 class="text-muted-foreground text-sm font-medium tracking-wide uppercase">{{ trans('group.scheduling_panel.mine.heading') }}</h3>
            <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.mine.subtitle') }}</p>
            <ShiftCard
                v-for="shift in mine"
                :key="shift.id"
                :shift="shift"
                :collects-visitor-count="collectsVisitorCount"
                :collects-extra-interactions="collectsExtraInteractions"
                :collects-visitor-provenance="collectsVisitorProvenance"
                @take="take"
                @drop="drop"
                @assign="openAssign"
                @remove="removeSeat"
                @edit="openShiftEdit"
                @delete="destroyShift"
                @record="record"
            />
        </section>

        <div v-if="canCreate && !scheduling.open" class="flex justify-end">
            <Button type="button" size="sm" class="gap-1.5" @click="openCreate">
                <PhPlus class="size-4" />
                {{ trans('group.scheduling_panel.new') }}
            </Button>
        </div>

        <!-- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and
             lead days for this Group's shift Reminders. Shown on the list view only, and only to
             a Scheduler / Chair (`canManageReminders`); the server re-checks on save. -->
        <Card v-if="canManageReminders && !scheduling.open">
            <CardHeader>
                <CardTitle>{{ trans('group.scheduling_panel.reminders.heading') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.reminders.description') }}</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :checked="reminderForm.reminders_enabled" @update:checked="(on: boolean) => (reminderForm.reminders_enabled = on)" />
                    {{ trans('group.scheduling_panel.reminders.enabled_label') }}
                </label>
                <div class="flex flex-col gap-1.5">
                    <Label for="reminder-lead-days">{{ trans('group.scheduling_panel.reminders.lead_days_label') }}</Label>
                    <Input id="reminder-lead-days" v-model.number="reminderForm.reminder_lead_days" type="number" min="1" max="90" class="w-24" />
                    <InputError :message="reminderForm.errors.reminder_lead_days" />
                </div>
                <div class="flex justify-end">
                    <Button type="button" size="sm" :disabled="reminderForm.processing" @click="saveReminders">
                        {{ trans('group.scheduling_panel.save') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Empty-desk settings (#487, ADR-0024 §7) — the schedule-admin's on/off switch,
             look-ahead, and the tick-rows marking which shift kinds the alert watches. Shown on
             the list view only, and only to a Scheduler / Chair (`canManageEmptyDesk`); the
             server re-checks on save. -->
        <Card v-if="canManageEmptyDesk && !scheduling.open">
            <CardHeader>
                <CardTitle>{{ trans('group.scheduling_panel.empty_desk.heading') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.empty_desk.description') }}</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="emptyDeskForm.empty_desk_alert_enabled"
                        @update:checked="(on: boolean) => (emptyDeskForm.empty_desk_alert_enabled = on)"
                    />
                    {{ trans('group.scheduling_panel.empty_desk.enabled_label') }}
                </label>
                <div class="flex flex-col gap-1.5">
                    <Label for="empty-desk-days-ahead">{{ trans('group.scheduling_panel.empty_desk.days_ahead_label') }}</Label>
                    <Input
                        id="empty-desk-days-ahead"
                        v-model.number="emptyDeskForm.empty_desk_days_ahead"
                        type="number"
                        min="1"
                        max="90"
                        class="w-24"
                    />
                    <InputError :message="emptyDeskForm.errors.empty_desk_days_ahead" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-medium">{{ trans('group.scheduling_panel.empty_desk.watched_label') }}</span>
                    <p v-if="emptyDesk.shiftKinds.length === 0" class="text-muted-foreground text-sm">
                        {{ trans('group.scheduling_panel.empty_desk.no_kinds') }}
                    </p>
                    <label v-for="kind in emptyDesk.shiftKinds" :key="kind.id" class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :checked="emptyDeskForm.watched_shift_kinds.includes(kind.id)"
                            @update:checked="(on: boolean) => toggleWatchedKind(kind.id, on)"
                        />
                        {{ kind.name }}
                    </label>
                </div>
                <div class="flex justify-end">
                    <Button type="button" size="sm" :disabled="emptyDeskForm.processing" @click="saveEmptyDesk">
                        {{ trans('group.scheduling_panel.save') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

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
                        <div class="flex shrink-0 flex-wrap items-center gap-1">
                            <!-- Email control (#490, ADR-0024 §6.3) — led by "Sign-ups on this
                                 Schedule", then the Group's Audiences. Present for every reader
                                 who can open the Schedule; the picker rule narrows the menu. -->
                            <EmailMenu
                                context="schedule"
                                :context-subject="String(scheduling.open.id)"
                                :group-name="groupName"
                                :roster="emailRoster"
                            />
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

            <!-- Shift authoring (#356 / #362 front end) — the "New shift" and "Bulk shifts"
                 controls on an opened Schedule, gated by the same schedule-admin verdict as
                 Schedule editing (`can.update`). Shown even on an empty Schedule so the first
                 Shift, single or in bulk, can be added. -->
            <div v-if="scheduling.open.can.update" class="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="openBulkAssign">
                    <PhUserPlus class="size-4" />
                    {{ trans('group.scheduling_panel.bulk_assign.open') }}
                </Button>
                <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="openBulk">
                    <PhStack class="size-4" />
                    {{ trans('group.scheduling_panel.bulk.open') }}
                </Button>
                <Button type="button" size="sm" class="gap-1.5" @click="openShiftCreate">
                    <PhPlus class="size-4" />
                    {{ trans('group.scheduling_panel.new_shift') }}
                </Button>
            </div>

            <!-- Bulk run report (#362 front end) — a run is N single writes plus this report:
                 how many were written or removed, and every skipped row with its reason, as
                 output rather than an error. Rides back in the shared `flash` prop; dismissable. -->
            <div v-if="shiftsReport" class="bg-muted/40 flex flex-col gap-2 rounded-md border p-4" role="status" aria-live="polite">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-rom-ink text-sm font-medium">
                        <template v-if="shiftsReport.created !== undefined">
                            {{ transChoice('group.scheduling_panel.bulk.report.created', shiftsReport.created) }}
                        </template>
                        <template v-else-if="shiftsReport.deleted !== undefined">
                            {{ transChoice('group.scheduling_panel.bulk.report.deleted', shiftsReport.deleted) }}
                        </template>
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-6 shrink-0"
                        :aria-label="trans('group.scheduling_panel.bulk.report.dismiss')"
                        @click="reportDismissed = true"
                    >
                        <PhX class="size-4" />
                    </Button>
                </div>
                <div v-if="shiftsReport.skipped.length" class="flex flex-col gap-1">
                    <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        {{ transChoice('group.scheduling_panel.bulk.report.skipped_heading', shiftsReport.skipped.length) }}
                    </p>
                    <ul class="text-muted-foreground flex flex-col gap-0.5 text-sm">
                        <li v-for="(row, index) in shiftsReport.skipped" :key="index">
                            <span v-if="row.date" class="text-rom-ink font-medium">{{ formatDate(row.date) }} — </span>
                            {{ trans(row.reason) }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bulk-assignment run report (#363 front end) — a placement run is N single writes
                 plus this report: how many seats were filled or cleared, and every skipped row
                 with its reason, as output rather than an error. Rides back in the shared `flash`
                 prop; dismissable. Kept distinct from the Shift report above. -->
            <div v-if="assignmentsReport" class="bg-muted/40 flex flex-col gap-2 rounded-md border p-4" role="status" aria-live="polite">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-rom-ink text-sm font-medium">
                        <template v-if="assignmentsReport.created !== undefined">
                            {{ transChoice('group.scheduling_panel.bulk_assign.report.placed', assignmentsReport.created) }}
                        </template>
                        <template v-else-if="assignmentsReport.removed !== undefined">
                            {{ transChoice('group.scheduling_panel.bulk_assign.report.removed', assignmentsReport.removed) }}
                        </template>
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-6 shrink-0"
                        :aria-label="trans('group.scheduling_panel.bulk_assign.report.dismiss')"
                        @click="assignReportDismissed = true"
                    >
                        <PhX class="size-4" />
                    </Button>
                </div>
                <div v-if="assignmentsReport.skipped.length" class="flex flex-col gap-1">
                    <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        {{ transChoice('group.scheduling_panel.bulk_assign.report.skipped_heading', assignmentsReport.skipped.length) }}
                    </p>
                    <ul class="text-muted-foreground flex flex-col gap-0.5 text-sm">
                        <li v-for="(row, index) in assignmentsReport.skipped" :key="index">
                            {{ trans(row.reason) }}
                        </li>
                    </ul>
                </div>
            </div>

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
                        :collects-visitor-count="collectsVisitorCount"
                        :collects-extra-interactions="collectsExtraInteractions"
                        :collects-visitor-provenance="collectsVisitorProvenance"
                        :email-group-name="groupName"
                        @take="take"
                        @drop="drop"
                        @assign="openAssign"
                        @remove="removeSeat"
                        @edit="openShiftEdit"
                        @delete="destroyShift"
                        @record="record"
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
                :group-name="groupName"
                @take="take"
                @drop="drop"
                @assign="openAssign"
                @remove="removeSeat"
                @edit="openShiftEdit"
                @delete="destroyShift"
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

        <!-- Shift authoring create/edit dialog (#356 front end) — one form, reused; opened by
             the "New shift" control or a per-Shift edit. Start / required end are org-wall-clock
             instants; capacity defaults to 1; kind is optional (from the Group's shift_kinds) and
             audience defaults to `group`. Server rejections — a Shift outside the Schedule's
             range, a capacity below the current Sign-up count — surface per field. -->
        <Dialog v-model:open="shiftDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ shiftDialogTitle }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitShift">
                    <div class="grid gap-2">
                        <Label for="shift-starts-at">{{ trans('group.scheduling_panel.shift_field.starts_at') }}</Label>
                        <Input id="shift-starts-at" v-model="shiftForm.starts_at" type="datetime-local" :step="TIME_STEP_SECONDS" required />
                        <InputError :message="shiftForm.errors.starts_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="shift-ends-at">{{ trans('group.scheduling_panel.shift_field.ends_at') }}</Label>
                        <Input id="shift-ends-at" v-model="shiftForm.ends_at" type="datetime-local" :step="TIME_STEP_SECONDS" required />
                        <InputError :message="shiftForm.errors.ends_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="shift-capacity">{{ trans('group.scheduling_panel.shift_field.capacity') }}</Label>
                        <Input id="shift-capacity" v-model.number="shiftForm.capacity" type="number" min="1" required />
                        <InputError :message="shiftForm.errors.capacity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="shift-kind">{{ trans('group.scheduling_panel.shift_field.kind') }}</Label>
                        <select id="shift-kind" v-model="shiftForm.shift_kind_id" :class="SELECT_CLASS">
                            <option :value="null">{{ trans('group.scheduling_panel.shift_field.kind_none') }}</option>
                            <option v-for="kind in scheduling.shift_kinds" :key="kind.id" :value="kind.id">{{ kind.name }}</option>
                        </select>
                        <InputError :message="shiftForm.errors.shift_kind_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="shift-audience">{{ trans('group.scheduling_panel.shift_field.audience') }}</Label>
                        <select id="shift-audience" v-model="shiftForm.audience" :class="SELECT_CLASS">
                            <option v-for="value in AUDIENCES" :key="value" :value="value">
                                {{ trans(`group.scheduling_panel.audience.${value}`) }}
                            </option>
                        </select>
                        <InputError :message="shiftForm.errors.audience" />
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="shiftForm.processing">{{ trans('group.scheduling_panel.save') }}</Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="shiftForm.processing" @click="closeShift">
                            {{ trans('group.scheduling_panel.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Bulk-create / bulk-delete dialog (#362 front end) — one filter, two verbs. It
             takes a kind, a start and end time, a capacity, a set of weekdays and a date
             range — no interval (ADR-0021 §2): a Shift lands on every matching weekday.
             "Create shifts" fans out; "Delete matching" removes every Shift on the same
             filter and confirms first, since it clears many rows at once. Server rejections
             surface per field; the run's report renders on the page above, not here. -->
        <Dialog v-model:open="bulkDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('group.scheduling_panel.bulk.title') }}</DialogTitle>
                </DialogHeader>
                <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.bulk.description') }}</p>
                <form class="flex flex-col gap-4" @submit.prevent="runBulk('create')">
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm leading-none font-medium">{{ trans('group.scheduling_panel.bulk.field.weekdays') }}</legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <label v-for="day in WEEKDAYS" :key="day.value" class="flex items-center gap-2 text-sm">
                                <input v-model="bulkForm.days_of_week" type="checkbox" :value="day.value" class="accent-rom-slate size-4" />
                                {{ day.label }}
                            </label>
                        </div>
                        <InputError :message="bulkForm.errors.days_of_week" />
                    </fieldset>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="bulk-starts-time">{{ trans('group.scheduling_panel.bulk.field.starts_time') }}</Label>
                            <Input id="bulk-starts-time" v-model="bulkForm.starts_time" type="time" :step="TIME_STEP_SECONDS" required />
                            <InputError :message="bulkForm.errors.starts_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-ends-time">{{ trans('group.scheduling_panel.bulk.field.ends_time') }}</Label>
                            <Input id="bulk-ends-time" v-model="bulkForm.ends_time" type="time" :step="TIME_STEP_SECONDS" required />
                            <InputError :message="bulkForm.errors.ends_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-from-date">{{ trans('group.scheduling_panel.bulk.field.from_date') }}</Label>
                            <Input id="bulk-from-date" v-model="bulkForm.from_date" type="date" required />
                            <InputError :message="bulkForm.errors.from_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-to-date">{{ trans('group.scheduling_panel.bulk.field.to_date') }}</Label>
                            <Input id="bulk-to-date" v-model="bulkForm.to_date" type="date" required />
                            <InputError :message="bulkForm.errors.to_date" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="bulk-capacity">{{ trans('group.scheduling_panel.bulk.field.capacity') }}</Label>
                        <Input id="bulk-capacity" v-model.number="bulkForm.capacity" type="number" min="1" required />
                        <InputError :message="bulkForm.errors.capacity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bulk-kind">{{ trans('group.scheduling_panel.bulk.field.kind') }}</Label>
                        <select id="bulk-kind" v-model="bulkForm.shift_kind_id" :class="SELECT_CLASS">
                            <option :value="null">{{ trans('group.scheduling_panel.bulk.field.kind_none') }}</option>
                            <option v-for="kind in scheduling.shift_kinds" :key="kind.id" :value="kind.id">{{ kind.name }}</option>
                        </select>
                        <InputError :message="bulkForm.errors.shift_kind_id" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" size="sm" :disabled="bulkForm.processing">{{ trans('group.scheduling_panel.bulk.create') }}</Button>
                        <Button type="button" variant="destructive" size="sm" :disabled="bulkForm.processing" @click="runBulk('delete')">
                            {{ trans('group.scheduling_panel.bulk.delete') }}
                        </Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="bulkForm.processing" @click="closeBulk">
                            {{ trans('group.scheduling_panel.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Bulk-place / bulk-remove a Member's Sign-ups dialog (#363 front end, ADR-0021 §5) —
             the Member-in-Schedule form: a member from the placeable roster, a set of weekdays,
             a start and end time, a date range, an interval (weekly / biweekly) and the anchor
             its biweekly phase counts from. "Place member" fans out; "Remove matching" unwinds
             the same filter and confirms first, since it clears someone else's seats, plural.
             The interval lives only here — no stored pattern (ADR-0021 §5). Server rejections
             surface per field; the run's report renders on the page above, not here. -->
        <Dialog v-model:open="bulkAssignDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('group.scheduling_panel.bulk_assign.title') }}</DialogTitle>
                </DialogHeader>
                <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.bulk_assign.description') }}</p>
                <form class="flex flex-col gap-4" @submit.prevent="runBulkAssign('place')">
                    <div class="grid gap-2">
                        <Label for="bulk-assign-member">{{ trans('group.scheduling_panel.bulk_assign.field.member') }}</Label>
                        <select id="bulk-assign-member" v-model="bulkAssignForm.member_id" :class="SELECT_CLASS">
                            <option :value="null" disabled>{{ trans('group.scheduling_panel.bulk_assign.field.member_none') }}</option>
                            <option v-for="candidate in scheduling.roster" :key="candidate.id" :value="candidate.id">
                                {{ candidate.first_name }} {{ candidate.last_name }}
                            </option>
                        </select>
                        <p v-if="!scheduling.roster.length" class="text-muted-foreground text-sm">
                            {{ trans('group.scheduling_panel.bulk_assign.field.member_empty') }}
                        </p>
                        <InputError :message="bulkAssignForm.errors.member_id" />
                    </div>
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm leading-none font-medium">
                            {{ trans('group.scheduling_panel.bulk_assign.field.weekdays') }}
                        </legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <label v-for="day in WEEKDAYS" :key="day.value" class="flex items-center gap-2 text-sm">
                                <input v-model="bulkAssignForm.days_of_week" type="checkbox" :value="day.value" class="accent-rom-slate size-4" />
                                {{ day.label }}
                            </label>
                        </div>
                        <InputError :message="bulkAssignForm.errors.days_of_week" />
                    </fieldset>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="bulk-assign-starts-time">{{ trans('group.scheduling_panel.bulk_assign.field.starts_time') }}</Label>
                            <Input id="bulk-assign-starts-time" v-model="bulkAssignForm.starts_time" type="time" :step="TIME_STEP_SECONDS" required />
                            <InputError :message="bulkAssignForm.errors.starts_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-assign-ends-time">{{ trans('group.scheduling_panel.bulk_assign.field.ends_time') }}</Label>
                            <Input id="bulk-assign-ends-time" v-model="bulkAssignForm.ends_time" type="time" :step="TIME_STEP_SECONDS" required />
                            <InputError :message="bulkAssignForm.errors.ends_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-assign-from-date">{{ trans('group.scheduling_panel.bulk_assign.field.from_date') }}</Label>
                            <Input id="bulk-assign-from-date" v-model="bulkAssignForm.from_date" type="date" required />
                            <InputError :message="bulkAssignForm.errors.from_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bulk-assign-to-date">{{ trans('group.scheduling_panel.bulk_assign.field.to_date') }}</Label>
                            <Input id="bulk-assign-to-date" v-model="bulkAssignForm.to_date" type="date" required />
                            <InputError :message="bulkAssignForm.errors.to_date" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="bulk-assign-interval">{{ trans('group.scheduling_panel.bulk_assign.field.interval') }}</Label>
                        <select id="bulk-assign-interval" v-model="bulkAssignForm.interval" :class="SELECT_CLASS">
                            <option v-for="value in INTERVALS" :key="value" :value="value">
                                {{ trans(`group.scheduling_panel.bulk_assign.interval.${value}`) }}
                            </option>
                        </select>
                        <InputError :message="bulkAssignForm.errors.interval" />
                    </div>
                    <!-- The anchor only phases the biweekly cadence, so it shows only then; it is
                         seeded to the range start on open, kept valid for a weekly run too. -->
                    <div v-if="bulkAssignForm.interval === 'biweekly'" class="grid gap-2">
                        <Label for="bulk-assign-anchor-date">{{ trans('group.scheduling_panel.bulk_assign.field.anchor_date') }}</Label>
                        <Input id="bulk-assign-anchor-date" v-model="bulkAssignForm.anchor_date" type="date" required />
                        <p class="text-muted-foreground text-xs">{{ trans('group.scheduling_panel.bulk_assign.field.anchor_hint') }}</p>
                        <InputError :message="bulkAssignForm.errors.anchor_date" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" size="sm" :disabled="bulkAssignForm.processing">
                            {{ trans('group.scheduling_panel.bulk_assign.place') }}
                        </Button>
                        <Button type="button" variant="destructive" size="sm" :disabled="bulkAssignForm.processing" @click="runBulkAssign('remove')">
                            {{ trans('group.scheduling_panel.bulk_assign.remove') }}
                        </Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="bulkAssignForm.processing" @click="closeBulkAssign">
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
