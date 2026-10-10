<script setup lang="ts">
// One Shift, rendered identically wherever it appears (#360, ADR-0021 §7). The Agenda
// lists these under day headings; the Calendar's day sheet shows the same card. Both views
// therefore show the same Shift facts — time range, kind, taken/capacity, the seated
// Members — and the same take / drop / assign / remove affordances, because they are this
// one component. It owns no policy: the server-sent `can` hints and `signup_id` decide what
// renders, and the parent handles each action (every mutation is re-checked server-side).
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import EmailMenu from '@/emailing/EmailMenu.vue';
import { formatShiftDate } from '@/scheduling/agenda';
import InputError from '@/components/InputError.vue';
import {
    buildRecordPayload,
    canSubmitRecord,
    COMMENT_MAX,
    draftFrom,
    hasUnsavedChanges,
    NO_RECORD_ERRORS,
    PROVENANCE_KEYS,
    recordErrorsFrom,
    type BoxValue,
    type RecordCallbacks,
    type RecordErrors,
    type RecordPayload,
    type SavedEntry,
} from '@/scheduling/recordDraft';
import { changeIsDisabled, commentPreview, entryState, lastEditedLine, openFormSeat, recordedTally, showsChange } from '@/scheduling/postShiftReport';
import { type SharedData, type ShiftAgendaItem, type ShiftSignUp, type VisitorProvenance } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhPencilSimple, PhTrash, PhUserPlus, PhX } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, useId, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        shift: ShiftAgendaItem;
        collectsVisitorCount?: boolean;
        collectsExtraInteractions?: boolean;
        collectsVisitorProvenance?: boolean;
        // The owning Group's name, set only where the card sits on an opened Schedule (#490,
        // ADR-0024 §6.4): its presence, with a seat taken, surfaces the Shift's Email control
        // ("Sign-ups on this Shift"). Null on the cross-Group "my Sign-ups" panel, where the
        // Shift is not an email entry point.
        emailGroupName?: string | null;
        // Whether the viewer may email the Schedule's Sign-ups (#513, ADR-0024 §6.4) — a Chair
        // or Scheduler of the Group, resolved once per opened Schedule. The only Audience here
        // is theirs to pick, so a plain member never sees the button: without this, a Schedule
        // of twenty Shifts would show twenty greyed buttons. Gates the Email control below,
        // on top of the seat-taken rule.
        canEmailSignups?: boolean;
        // Name the Shift's date above the time (#553). Off by default, so the Agenda and the
        // Calendar — where the day is already the heading over the card — read unchanged. The
        // cross-Group "My sign-ups" panel turns it on: it lists Shifts across Schedules with no
        // day heading of its own, so without the date two cards can share a time and mean
        // different days.
        showDate?: boolean;
        // Whether the self-serve owner's Edit / Delete controls may render (#585, ADR-0026 §1).
        // On by the opened Schedule's Agenda and Calendar, where the station picker's kinds are in
        // hand; off on the cross-Schedule "My sign-ups" panel, which carries no kind list to edit
        // against. Combined with `shift.can.manageSelfServe` — both must hold to show the controls.
        allowSelfServeControls?: boolean;
    }>(),
    {
        collectsVisitorCount: false,
        collectsExtraInteractions: false,
        collectsVisitorProvenance: false,
        emailGroupName: null,
        canEmailSignups: false,
        showDate: false,
        allowSelfServeControls: false,
    },
);

const emit = defineEmits<{
    take: [shift: ShiftAgendaItem];
    drop: [shift: ShiftAgendaItem];
    assign: [shift: ShiftAgendaItem];
    remove: [signUpId: number];
    // Change a seat's Tour (#791, ADR-0033 §6) — the seat-holder's own, or any seat for a
    // schedule admin; the parent opens the picker.
    changeTour: [shift: ShiftAgendaItem, signUp: ShiftSignUp];
    edit: [shift: ShiftAgendaItem];
    delete: [shift: ShiftAgendaItem];
    // The self-serve owner's own controls (#585, ADR-0026 §1) — distinct from the Scheduler's
    // edit/delete above, because they route through the self-serve seam, not `shifts.*`.
    editSelfServe: [shift: ShiftAgendaItem];
    deleteSelfServe: [shift: ShiftAgendaItem];
    record: [payload: { signUpId: number } & RecordPayload, callbacks: RecordCallbacks];
}>();

const page = usePage<SharedData>();

// Shift times are instants read on the org wall clock, so 10am is 10am at the museum
// wherever the reader sits (matches the Agenda's grouping day).
const timeZone = page.props.timezone;
const formatTime = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { timeStyle: 'short', timeZone }).format(new Date(iso));
const timeRange = (starts: string, ends: string) =>
    trans('group.scheduling_panel.agenda.time_range', { start: formatTime(starts), end: formatTime(ends) });

// The Shift's date, spelled the way the Agenda's day headings are, shown only where `showDate`
// is set (the cross-Schedule My-sign-ups panel). Read on the org wall clock from the same
// instant, so the card names the day the Agenda already filed the Shift under.
const shiftDate = computed(() => formatShiftDate(props.shift.starts_at, page.props.locale, timeZone));

const signUpName = (signUp: ShiftSignUp) => `${signUp.first_name} ${signUp.last_name}`;

// A Booking's order date (#795) is a plain calendar date, parsed as local midnight so no zone
// shift lands it on the day before.
const formatOrderDate = (date: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium' }).format(new Date(`${date}T00:00:00`));

// The order line (#795, ADR-0032 §5): the number and date the server sent to a Booker,
// Statistician, Chair or super-tier; empty when neither was recorded.
const orderLine = computed(() => {
    const officer = props.shift.booking?.officer;
    if (!officer) return '';

    return [
        officer.order_number ? trans('group.bookings.order', { number: officer.order_number }) : null,
        officer.order_date ? trans('group.bookings.ordered_on', { date: formatOrderDate(officer.order_date) }) : null,
    ]
        .filter((part) => part !== null)
        .join(', ');
});

// The viewer's own seat (#445, ADR-0023 §5). Seats carry the member id; the signed-in Member is
// auth.user.
const isOwnSeat = (signUp: ShiftSignUp) => signUp.id === page.props.auth.user.id;

// --- The Post-shift report (#652, PRD #651, ADR-0023 §5) ------------------------------------

// The section renders where the Group collects a count and the server says this viewer reads the
// report (a seat-holder or a schedule admin). The server sends the seat numbers on the same rule.
// `collectsVisitorCount` is only set where the parent handles `record` (the Agenda and My
// sign-ups), so the Calendar day sheet and the foreign band show no section they cannot save.
const showsReport = computed(() => props.collectsVisitorCount && props.shift.can.readReport);

const tally = computed(() => recordedTally(props.shift.signups));

// The seat a Change button reopened: the viewer's own, or any seat for an Officer (#450, #653).
// Null → the own seat opens by itself inside its window while it has no count; see
// {@see openFormSeat}.
const editingSeatId = ref<number | null>(null);

// A seat removed while its form is open (#668) closes that form, so the viewer's own form can
// open by itself again, and a later seat for the same Member does not reopen it.
watch(
    () => props.shift.signups.map((signUp) => signUp.id),
    (seatIds) => {
        if (editingSeatId.value !== null && !seatIds.includes(editingSeatId.value)) editingSeatId.value = null;
    },
);

// The seat the last save on this card wrote (#668), which shows "Saved." beside its entry until
// the next page load or the next Change.
const savedSeatId = ref<number | null>(null);

// The one seat showing the form, if any. `can.record` is the server's word that the viewer's own
// seat is inside its sign-out window.
const formSeatId = computed(() =>
    showsReport.value
        ? openFormSeat({
              seats: props.shift.signups,
              ownSeatId: props.shift.signup_id !== null ? page.props.auth.user.id : null,
              canRecordOwn: props.shift.can.record,
              editingSeatId: editingSeatId.value,
          })
        : null,
);

// Each seat with how its entry reads: the form, a summary, or "No count yet"; and whether its
// Change is disabled because the open form has unsaved typing (#668).
const entries = computed(() =>
    props.shift.signups.map((signUp) => {
        const state = entryState(signUp, formSeatId.value);

        return { signUp, state, changeDisabled: changeIsDisabled(state, unsaved.value) };
    }),
);

const formSeat = computed(() => props.shift.signups.find((signUp) => signUp.id === formSeatId.value) ?? null);

// An Officer's correction of another Member's seat — named above the form so it is never
// mistaken for the viewer's own.
const correctingSeat = computed(() => (formSeat.value && !isOwnSeat(formSeat.value) ? formSeat.value : null));

// GDR's five origin fields, in the order the record form lists them.
// Each label key is the field key with `visitors_` swapped for `provenance_`.
const provenanceFields = PROVENANCE_KEYS.map((key) => ({
    key,
    labelKey: `group.scheduling_panel.agenda.sign_out.${key.replace('visitors_', 'provenance_')}`,
}));

// The five origins already recorded on a seat, null-safe, so the form pre-fills a change rather
// than making anyone retype.
const provenanceOf = (seat: ShiftSignUp): VisitorProvenance => ({
    visitors_france_europe: seat.visitors_france_europe ?? null,
    visitors_quebec: seat.visitors_quebec ?? null,
    visitors_toronto: seat.visitors_toronto ?? null,
    visitors_rest_of_canada: seat.visitors_rest_of_canada ?? null,
    visitors_other_countries: seat.visitors_other_countries ?? null,
});

type RecordTarget = { signUpId: number } & SavedEntry;

// The seat the form is writing, normalised to one shape. The write names the seat's Sign-up id:
// the viewer's own from `shift.signup_id`, another seat's from the Officer-only `signup_id`. Null
// → no form. Both post through the one PATCH seam.
const recordTarget = computed<RecordTarget | null>(() => {
    const seat = formSeat.value;
    if (seat === null) return null;

    const signUpId = isOwnSeat(seat) ? props.shift.signup_id : seat.signup_id;
    if (signUpId === null || signUpId === undefined) return null;

    return {
        signUpId,
        visitor_count: seat.visitor_count ?? null,
        extra_interaction_count: seat.extra_interaction_count ?? null,
        comment: seat.comment ?? null,
        ...provenanceOf(seat),
    };
});

// The boxes, seeded from whichever seat the form now targets so a change edits rather than
// retypes ({@see draftFrom}). The count box stays required (the Record shift button waits for it —
// the forcing function that carries the count on 96-98% of shifts); the extra box is optional; the
// five origins are seeded too. A box holds a string until the user types, then a number (#646); an
// empty box stays distinct from a typed zero.
const draft = ref<BoxValue>('');
const extraDraft = ref<BoxValue>('');
const provenanceDrafts = ref(draftFrom(null).provenance);
// The viewer's own comment (#655), seeded from the seat so Change edits it.
const commentDraft = ref<string | number>('');

// Put the boxes back to what the target seat has on file, with no errors.
const seedForm = () => {
    const seeded = draftFrom(recordTarget.value);
    recordErrors.value = NO_RECORD_ERRORS;
    draft.value = seeded.count;
    extraDraft.value = seeded.extra;
    provenanceDrafts.value = seeded.provenance;
    commentDraft.value = seeded.comment;
};

// The server's refusal of this card's last save (#649), one message per box. It lives on the card,
// not the page's shared error bag, so the other copy of the same Shift never shows it.
const recordErrors = ref<RecordErrors>(NO_RECORD_ERRORS);

// Reseed on what the target holds, not on its identity: every page reload builds a fresh object,
// and a refused save (#649) reloads the page with the seat unchanged. Watching the object would
// wipe the typed values the person is about to correct. A different seat, or new recorded values,
// also starts with no errors.
const recordTargetKey = computed(() => JSON.stringify(recordTarget.value));

watch(recordTargetKey, seedForm, { immediate: true });

const recordDraft = computed(() => ({
    count: draft.value,
    extra: extraDraft.value,
    provenance: provenanceDrafts.value,
    comment: String(commentDraft.value),
}));

// The comment box shows on the viewer's own seat only (#655). An officer's correction has none,
// so it never changes a volunteer's words; the server refuses a comment there too.
const writesComment = computed(() => correctingSeat.value === null);

const recordFlags = computed(() => ({
    collectsExtraInteractions: props.collectsExtraInteractions,
    collectsVisitorProvenance: props.collectsVisitorProvenance,
    writesComment: writesComment.value,
}));

// The count is required; on GDR the five origins must be filled (the server checks their sum).
const canSubmit = computed(() => canSubmitRecord(recordDraft.value, recordFlags.value));

// Typing the open form holds that is not saved yet (#668). While there is any, Change on every
// other entry is disabled, so moving the form never loses it.
const unsaved = computed(() => hasUnsavedChanges(recordDraft.value, recordTarget.value));

// Reopen one seat's form from its Change button. The seat's `can_record` (the server's verdict,
// #653) shows the button and the SignUpPolicy re-checks the write. Cancel closes it and returns
// the card to its default: the own seat's form if it is still unrecorded in its window, with the
// typing undone.
const openForm = (signUp: ShiftSignUp) => {
    savedSeatId.value = null;
    editingSeatId.value = signUp.id;
};

const cancelForm = () => {
    editingSeatId.value = null;
    seedForm();
};

// A refusal fills this card's errors and leaves the typed values in the boxes (a PATCH keeps page
// state). A reopened form stays open until the server accepts it, so a refusal shows inside it. A
// first save collapses by itself: the seat now has a count, so it reads as a summary.
const submitRecord = () => {
    if (!canSubmit.value || recordTarget.value === null) return;

    const seatId = formSeatId.value;

    emit(
        'record',
        { signUpId: recordTarget.value.signUpId, ...buildRecordPayload(recordDraft.value, recordFlags.value) },
        {
            onSuccess: () => {
                recordErrors.value = NO_RECORD_ERRORS;
                editingSeatId.value = null;
                savedSeatId.value = seatId;
            },
            onError: (errors) => {
                recordErrors.value = recordErrorsFrom(errors);
            },
        },
    );
};

// A recorded entry's summary: the count, singular for one (#668), then the extra count and GDR's
// five origins where they are recorded.
const entrySummary = (signUp: ShiftSignUp): string => {
    const count = signUp.visitor_count ?? 0;
    const parts = [transChoice('group.scheduling_panel.agenda.sign_out.recorded', count, { count: String(count) })];
    const extra = signUp.extra_interaction_count ?? null;
    if (extra !== null) parts.push(trans('group.scheduling_panel.agenda.sign_out.extra_recorded', { count: String(extra) }));
    provenanceFields.forEach((field) => {
        const value = signUp[field.key] ?? null;
        if (value !== null) parts.push(`${trans(field.labelKey)} ${value}`);
    });

    return parts.join(' · ');
};

// "Last edited by [name] · [time]" under a saved entry (#654), on the org wall clock; null for an
// entry nobody has saved, which shows no line.
const lastEdited = (signUp: ShiftSignUp): string | null => {
    const line = lastEditedLine(signUp, page.props.locale, timeZone);

    return line && trans('group.scheduling_panel.agenda.sign_out.last_edited', line);
};

const entryName = (signUp: ShiftSignUp): string =>
    isOwnSeat(signUp) ? trans('group.scheduling_panel.agenda.sign_out.you', { name: signUpName(signUp) }) : signUpName(signUp);

// A form reopened by Change says Save changes; a first save says Record shift.
const reopened = computed(() => editingSeatId.value !== null);

// Why the button is still disabled — read out with it, so nobody guesses. It names the button's
// own action (#668): record the shift, or save the changes.
const disabledReason = computed(() => {
    if (reopened.value) {
        return trans(
            props.collectsVisitorProvenance
                ? 'group.scheduling_panel.agenda.sign_out.save_needs_provenance'
                : 'group.scheduling_panel.agenda.sign_out.save_needs_count',
        );
    }

    return trans(
        props.collectsVisitorProvenance
            ? 'group.scheduling_panel.agenda.sign_out.record_needs_provenance'
            : 'group.scheduling_panel.agenda.sign_out.record_needs_count',
    );
});

// The help under the count box: the viewer's own visitors, or on an officer's correction, the
// volunteer's (#668).
const countHelp = computed(() =>
    correctingSeat.value
        ? trans('group.scheduling_panel.agenda.sign_out.count_help_correcting', { name: signUpName(correctingSeat.value) })
        : trans('group.scheduling_panel.agenda.sign_out.count_help'),
);

// "N of 2,000 characters" under the comment box (#668), numbers in the viewer's locale.
const commentCount = computed(() => {
    const format = new Intl.NumberFormat(page.props.locale);

    return trans('group.scheduling_panel.agenda.sign_out.comment_count', {
        count: format.format(String(commentDraft.value).length),
        max: format.format(COMMENT_MAX),
    });
});

// Unique ids for the help texts the boxes and button point at. The same Shift can render twice
// (My sign-ups and the Agenda), so the id comes from the component, not the Shift.
const formId = useId();
</script>

<template>
    <Card>
        <CardContent class="flex flex-col gap-2 py-4">
            <!-- The date, on the My-sign-ups panel only (#553): that panel crosses Schedules with
                 no day heading of its own, so the card names its own day. Off in the Agenda and
                 Calendar, where the day already heads the card. -->
            <p v-if="showDate" class="text-muted-foreground text-sm font-medium">{{ shiftDate }}</p>
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-rom-ink font-medium">{{ timeRange(shift.starts_at, shift.ends_at) }}</span>
                    <span v-if="shift.kind" class="text-muted-foreground text-sm">{{ shift.kind }}</span>
                    <!-- A group tour's Tour (#795), for every reader. -->
                    <span v-if="shift.booking" class="text-rom-ink text-sm">· {{ shift.booking.tour }}</span>
                </div>
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('group.scheduling_panel.agenda.seats', { taken: String(shift.taken), capacity: String(shift.capacity) }) }}
                </span>
            </div>

            <!-- A group tour's client half (#795, ADR-0032 §5) — only what the server sent this
                 viewer: the client, visitors, type, leader and comments to the Group's Members, the
                 order line to a Booker, Statistician or Chair. Client, leader, comments and the type
                 name are content, shown as-authored. -->
            <div v-if="shift.booking?.details" class="flex flex-col gap-0.5 text-sm">
                <p class="text-rom-ink font-medium break-words">{{ shift.booking.details.client }}</p>
                <p class="text-muted-foreground">
                    {{ transChoice('group.bookings.visitors', shift.booking.details.visitors, { count: String(shift.booking.details.visitors) }) }}
                    · {{ shift.booking.details.type }}
                    <template v-if="shift.booking.details.leader">
                        · {{ trans('group.bookings.leader', { leader: shift.booking.details.leader }) }}
                    </template>
                </p>
                <p v-if="orderLine" class="text-muted-foreground">{{ orderLine }}</p>
                <p v-if="shift.booking.details.comments" class="text-rom-ink break-words whitespace-pre-line">{{ shift.booking.details.comments }}</p>
            </div>

            <!-- Who is on the floor (#357) — visible to every reader who can read the
                 Schedule, non-members included. Each seat is a chip; a schedule admin gets a
                 remove (×) on every seat (officer removal, #359). An honest empty line otherwise. -->
            <div v-if="shift.signups.length" class="flex flex-wrap items-center gap-1.5">
                <span class="text-muted-foreground text-sm font-medium">{{ trans('group.scheduling_panel.agenda.sign_up.signed_up_label') }}:</span>
                <!-- A chip wraps inside the card on a phone (#648) rather than running off-screen. Its
                     remove (×) is a 44px tap target on a phone (#668); negative margins keep the
                     chip its own size. -->
                <Badge
                    v-for="signUp in shift.signups"
                    :key="signUp.id"
                    variant="secondary"
                    class="max-w-full flex-wrap gap-1 font-normal whitespace-normal"
                >
                    {{ signUpName(signUp) }}
                    <!-- The Tour this seat gives (#790, ADR-0033 §2), as-authored. -->
                    <span v-if="signUp.tour" class="text-muted-foreground">· {{ signUp.tour }}</span>
                    <!-- Change the seat's Tour (#791) — the seat-holder until the start, a schedule admin
                         any time (`can_change_tour`, re-checked on PATCH). -->
                    <button
                        v-if="signUp.can_change_tour"
                        type="button"
                        class="hover:text-rom-ink -my-3.5 inline-flex size-11 items-center justify-center rounded-full transition-colors sm:-my-1 sm:size-6"
                        :aria-label="trans('group.scheduling_panel.tour.change')"
                        @click="emit('changeTour', shift, signUp)"
                    >
                        <PhPencilSimple class="size-4" />
                    </button>
                    <!-- The Objects on this seat (#586, ADR-0026 §3) — what the Member is taking
                         onto the floor, named under them so a colleague sees what is already out
                         before they pick. A retired Object still shows its name here. -->
                    <span v-if="signUp.objects && signUp.objects.length" class="text-muted-foreground">
                        · {{ signUp.objects.map((object) => object.name).join(', ') }}
                    </span>
                    <button
                        v-if="signUp.signup_id"
                        type="button"
                        class="hover:text-destructive -my-3.5 -mr-3.5 inline-flex size-11 items-center justify-center rounded-full transition-colors sm:-my-1 sm:-mr-1.5 sm:size-6"
                        :aria-label="trans('group.scheduling_panel.agenda.assign.remove')"
                        @click="emit('remove', signUp.signup_id)"
                    >
                        <PhX class="size-4" />
                    </button>
                </Badge>
            </div>
            <p v-else class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.agenda.sign_up.nobody') }}</p>

            <!-- Take / drop, from the same place. `signup_id` means "I hold a seat";
                 `can.signUp` means "a free seat is offered to me". A full Shift the viewer has
                 no seat on shows as full with neither button. Self-service closes once the Shift
                 has started (#554), so take, drop, and the full label all hide then; the Officer's
                 assign (#359) and the Post-shift report below stay. The Scheduler's assign sits beside
                 them — the officer path onto a Shift with a free seat. -->
            <div class="flex flex-wrap items-center gap-2">
                <template v-if="!shift.has_started">
                    <Button v-if="shift.signup_id !== null" type="button" variant="outline" size="sm" @click="emit('drop', shift)">
                        {{ trans('group.scheduling_panel.agenda.sign_up.drop') }}
                    </Button>
                    <Button v-else-if="shift.can.signUp" type="button" size="sm" @click="emit('take', shift)">
                        {{ trans('group.scheduling_panel.agenda.sign_up.take') }}
                    </Button>
                    <span v-else-if="shift.taken >= shift.capacity" class="text-muted-foreground text-sm font-medium">
                        {{ trans('group.scheduling_panel.agenda.sign_up.full') }}
                    </span>
                </template>
                <Button v-if="shift.can.assign" type="button" variant="outline" size="sm" class="gap-1.5" @click="emit('assign', shift)">
                    <PhUserPlus class="size-4" />
                    {{ trans('group.scheduling_panel.agenda.assign.place') }}
                </Button>
                <!-- Shift authoring (#356 front end) — a schedule admin edits and, at zero
                     Sign-ups, deletes the Shift. Server-gated via `can.update` / `can.delete`,
                     so a foreign Shift and an ordinary reader see neither. -->
                <Button v-if="shift.can.update" type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('edit', shift)">
                    <PhPencilSimple class="size-4" />
                    {{ trans('group.scheduling_panel.edit') }}
                </Button>
                <Button v-if="shift.can.delete" type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('delete', shift)">
                    <PhTrash class="size-4" />
                    {{ trans('group.scheduling_panel.delete') }}
                </Button>
                <!-- A group tour's Edit / Delete (#796) — a Booker, Statistician, Chair or super-tier
                     changes the Booking, never the Shift. They ride the same `edit` / `delete` emits;
                     the parent routes a Booking Shift to the Booking form. -->
                <Button v-if="shift.booking?.edit" type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('edit', shift)">
                    <PhPencilSimple class="size-4" />
                    {{ trans('group.bookings.edit') }}
                </Button>
                <Button v-if="shift.booking?.can_delete" type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('delete', shift)">
                    <PhTrash class="size-4" />
                    {{ trans('group.bookings.delete') }}
                </Button>
                <!-- The self-serve owner's Edit / Delete (#585, ADR-0026 §1) — shown to the Member
                     who wrote the Shift, until it starts (`can.manageSelfServe`). They route through
                     the self-serve seam, so they are their own emits, not the Scheduler's above.
                     The owner rule binds super-tier too (#647), so an officer sees both only on a
                     Shift they own themselves. -->
                <template v-if="allowSelfServeControls && shift.can.manageSelfServe">
                    <Button type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('editSelfServe', shift)">
                        <PhPencilSimple class="size-4" />
                        {{ trans('group.scheduling_panel.self_serve.edit') }}
                    </Button>
                    <Button type="button" variant="ghost" size="sm" class="gap-1.5" @click="emit('deleteSelfServe', shift)">
                        <PhTrash class="size-4" />
                        {{ trans('group.scheduling_panel.self_serve.delete') }}
                    </Button>
                </template>
                <!-- Email control (#490, #513, ADR-0024 §6.4) — one Audience, "Sign-ups on this
                     Shift". Shown only on an opened Schedule (`emailGroupName` set), only once a
                     seat is taken, and only to a Chair or Scheduler (`canEmailSignups`): they
                     alone may pick it, so a plain member sees no greyed button per Shift. No
                     hand-pick here, so the composer's Add-people pool is empty. -->
                <EmailMenu
                    v-if="emailGroupName !== null && canEmailSignups && shift.signups.length"
                    context="shift"
                    :context-subject="String(shift.id)"
                    :group-name="emailGroupName"
                    :roster="[]"
                />
            </div>

            <!-- The Post-shift report (#652, PRD #651, ADR-0023 §5) — one entry per seat, below
                 the shift details, where the Group collects a count and the server says this
                 viewer reads the report. An entry is the form, a summary of its numbers, or "No
                 count yet"; one form at most per card. The viewer's own unrecorded seat opens as
                 the form inside its sign-out window; Change reopens a recorded one. Co-volunteers
                 see each other's counts, so a double count shows. -->
            <section v-if="showsReport" class="flex flex-col gap-3 border-t pt-3" :aria-labelledby="`${formId}-heading`">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h4 :id="`${formId}-heading`" class="text-rom-ink text-sm font-semibold">
                        {{ trans('group.scheduling_panel.agenda.sign_out.heading') }}
                    </h4>
                    <span class="text-muted-foreground text-sm tabular-nums">
                        {{
                            transChoice('group.scheduling_panel.agenda.sign_out.progress', tally.recorded, {
                                recorded: String(tally.recorded),
                                total: String(tally.total),
                            })
                        }}
                    </span>
                </div>
                <ul class="flex flex-col gap-3">
                    <!-- The open form is set apart from the entries above and below it (#668). -->
                    <li v-for="{ signUp, state, changeDisabled } in entries" :key="signUp.id" :class="{ 'border-y py-3': state === 'form' }">
                        <!-- The form. `novalidate` lets a decimal or negative reach the server,
                             whose message shows under the box in the app's language (#649),
                             instead of the browser's own popup. The server requires, whole-checks,
                             bounds and re-authorises every write regardless. -->
                        <form
                            v-if="state === 'form'"
                            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start"
                            novalidate
                            @submit.prevent="submitRecord"
                        >
                            <!-- Whose seat this is. An Officer's correction names the Member, so it
                                 is never mistaken for the viewer's own. The boxes in a row line up
                                 at the top (#668), so help text under one never pushes the next. -->
                            <p class="text-rom-ink w-full text-sm font-medium">
                                {{
                                    correctingSeat
                                        ? trans('group.scheduling_panel.agenda.sign_out.correcting', { name: signUpName(correctingSeat) })
                                        : entryName(signUp)
                                }}
                            </p>
                            <label class="flex flex-col gap-1">
                                <span class="text-muted-foreground text-sm font-medium">{{
                                    trans('group.scheduling_panel.agenda.sign_out.count_label')
                                }}</span>
                                <Input
                                    v-model="draft"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    step="1"
                                    class="h-11 w-full sm:h-9 sm:w-40"
                                    :aria-describedby="`${formId}-count-help`"
                                    :placeholder="trans('group.scheduling_panel.agenda.sign_out.placeholder')"
                                />
                                <!-- Against double counting (#651): a shared station counts each
                                     visitor once. An officer's correction speaks of the volunteer's
                                     count (#668). -->
                                <span :id="`${formId}-count-help`" class="text-muted-foreground text-sm sm:w-40">
                                    {{ countHelp }}
                                </span>
                                <InputError :message="recordErrors.count" class="sm:w-40" />
                            </label>
                            <!-- The tour-leading second box (#447, ADR-0023 §2) — visitors served
                                 outside the tour. Optional: it never gates the button below. Shown
                                 only where the Group collects the split. -->
                            <label v-if="collectsExtraInteractions" class="flex flex-col gap-1">
                                <span class="text-muted-foreground text-sm font-medium">{{
                                    trans('group.scheduling_panel.agenda.sign_out.extra_label')
                                }}</span>
                                <Input
                                    v-model="extraDraft"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    step="1"
                                    class="h-11 w-full sm:h-9 sm:w-40"
                                    :placeholder="trans('group.scheduling_panel.agenda.sign_out.extra_placeholder')"
                                />
                                <InputError :message="recordErrors.extra" class="sm:w-40" />
                            </label>
                            <!-- GDR's five visitor origins (#448, ADR-0023 §3), one box each. Shown
                                 only where the Group collects provenance; the five are required
                                 together and must sum to the count, which the server checks and
                                 names in one message (#649). Their own row under a heading, at equal
                                 widths (#668). Each label is a subgrid of the two rows, so a label
                                 that wraps never pushes its box below the others. -->
                            <fieldset v-if="collectsVisitorProvenance" class="flex w-full flex-col gap-2">
                                <legend class="text-rom-ink mb-2 text-sm font-medium">
                                    {{ trans('group.scheduling_panel.agenda.sign_out.provenance_heading') }}
                                </legend>
                                <div class="grid grid-cols-1 gap-x-3 gap-y-3 sm:grid-cols-5 sm:gap-y-1">
                                    <label
                                        v-for="field in provenanceFields"
                                        :key="field.key"
                                        class="flex flex-col gap-1 sm:row-span-2 sm:grid sm:grid-rows-subgrid"
                                    >
                                        <span class="text-muted-foreground text-sm font-medium">{{ trans(field.labelKey) }}</span>
                                        <Input
                                            v-model="provenanceDrafts[field.key]"
                                            type="number"
                                            inputmode="numeric"
                                            min="0"
                                            step="1"
                                            class="h-11 w-full sm:h-9"
                                        />
                                    </label>
                                </div>
                                <InputError :message="recordErrors.provenance" />
                            </fieldset>
                            <!-- The viewer's own comment (#655): optional, at most 2,000 characters,
                                 read only by its author and a schedule admin. No box on an
                                 officer's correction. The box stops at the limit and counts the
                                 characters (#668); its text is the summary size. -->
                            <label v-if="writesComment" class="flex w-full flex-col gap-1">
                                <span class="text-muted-foreground text-sm font-medium">{{
                                    trans('group.scheduling_panel.agenda.sign_out.comment_label')
                                }}</span>
                                <Textarea
                                    v-model="commentDraft"
                                    rows="3"
                                    :maxlength="COMMENT_MAX"
                                    :aria-describedby="`${formId}-comment-help ${formId}-comment-count`"
                                />
                                <span :id="`${formId}-comment-help`" class="text-muted-foreground text-sm">
                                    {{ trans('group.scheduling_panel.agenda.sign_out.comment_help') }}
                                </span>
                                <span :id="`${formId}-comment-count`" class="text-muted-foreground text-sm tabular-nums">
                                    {{ commentCount }}
                                </span>
                                <InputError :message="recordErrors.comment" />
                            </label>
                            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                                <!-- Record shift waits for a count (the forcing function); a
                                     reopened form says Save changes and can be cancelled. So can a
                                     form with unsaved typing (#668), which Cancel undoes. Full
                                     width and 48px tall on a phone. -->
                                <Button
                                    type="submit"
                                    class="h-12 w-full sm:h-9 sm:w-auto"
                                    :disabled="!canSubmit"
                                    :aria-describedby="canSubmit ? undefined : `${formId}-disabled-reason`"
                                >
                                    {{
                                        trans(
                                            reopened
                                                ? 'group.scheduling_panel.agenda.sign_out.save'
                                                : 'group.scheduling_panel.agenda.sign_out.record',
                                        )
                                    }}
                                </Button>
                                <Button
                                    v-if="reopened || unsaved"
                                    type="button"
                                    variant="ghost"
                                    class="h-11 w-full sm:h-9 sm:w-auto"
                                    @click="cancelForm"
                                >
                                    {{ trans('group.scheduling_panel.agenda.sign_out.cancel') }}
                                </Button>
                                <span v-if="!canSubmit" :id="`${formId}-disabled-reason`" class="text-muted-foreground text-sm">
                                    {{ disabledReason }}
                                </span>
                            </div>
                        </form>
                        <!-- A summary of a recorded seat, or "No count yet". Change sits below
                             the text on a phone and beside it from sm up (#668). -->
                        <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-x-4">
                            <div class="flex min-w-0 flex-col sm:flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-rom-ink text-sm font-medium">{{ entryName(signUp) }}</span>
                                    <!-- "Saved." after this card's save (#668), as on the settings pages. -->
                                    <span v-if="savedSeatId === signUp.id" role="status" class="text-muted-foreground text-sm">
                                        {{ trans('group.scheduling_panel.agenda.sign_out.saved') }}
                                    </span>
                                </span>
                                <span class="text-muted-foreground text-sm tabular-nums">
                                    {{ state === 'summary' ? entrySummary(signUp) : trans('group.scheduling_panel.agenda.sign_out.no_count') }}
                                </span>
                                <!-- A one-line preview of the comment (#655). The server sends it only
                                     to its author and a schedule admin, so a co-volunteer sees none. -->
                                <span v-if="commentPreview(signUp)" class="text-muted-foreground truncate text-sm italic">
                                    {{ commentPreview(signUp) }}
                                </span>
                                <span v-if="lastEdited(signUp)" class="text-muted-foreground text-sm">{{ lastEdited(signUp) }}</span>
                            </div>
                            <!-- Change is disabled while the open form has unsaved typing (#668),
                                 so moving the form never loses it; the hint says why. -->
                            <div v-if="showsChange(signUp, state)" class="flex flex-col items-start gap-1 sm:items-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 sm:h-8"
                                    :disabled="changeDisabled"
                                    :aria-describedby="changeDisabled ? `${formId}-change-blocked-${signUp.id}` : undefined"
                                    @click="openForm(signUp)"
                                >
                                    {{ trans('group.scheduling_panel.agenda.sign_out.change') }}
                                </Button>
                                <span v-if="changeDisabled" :id="`${formId}-change-blocked-${signUp.id}`" class="text-muted-foreground text-sm">
                                    {{ trans('group.scheduling_panel.agenda.sign_out.change_blocked') }}
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </CardContent>
    </Card>
</template>
