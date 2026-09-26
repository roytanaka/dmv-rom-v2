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
import EmailMenu from '@/emailing/EmailMenu.vue';
import { formatShiftDate } from '@/scheduling/agenda';
import { buildRecordPayload, canSubmitRecord, type BoxValue } from '@/scheduling/recordDraft';
import { withinSignOutWindow } from '@/scheduling/signOut';
import { type SharedData, type ShiftAgendaItem, type ShiftSignUp, type VisitorProvenance } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhPencilSimple, PhTrash, PhUserPlus, PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

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
    edit: [shift: ShiftAgendaItem];
    delete: [shift: ShiftAgendaItem];
    // The self-serve owner's own controls (#585, ADR-0026 §1) — distinct from the Scheduler's
    // edit/delete above, because they route through the self-serve seam, not `shifts.*`.
    editSelfServe: [shift: ShiftAgendaItem];
    deleteSelfServe: [shift: ShiftAgendaItem];
    record: [payload: { signUpId: number; count: number; extra: number | null; provenance: VisitorProvenance | null }];
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

// The viewer's own seat, so their recorded count reads on their own chip (#445, ADR-0023 §5).
// Seats carry the member id; the signed-in Member is auth.user.
const isOwnSeat = (signUp: ShiftSignUp) => signUp.id === page.props.auth.user.id;

// --- Recording the numbers (#445, #450, ADR-0023 §5) — one form, two ways in ----------------

// The seat-holder's own sign-out window: the Group collects a count, the viewer holds a seat
// here, and the five-minute window has opened. The window is the client's copy of the server
// rule ({@see withinSignOutWindow}); the server's `can.record` and the Form Request enforce
// every write regardless.
const showSignOut = computed(
    () => props.collectsVisitorCount && props.shift.signup_id !== null && withinSignOutWindow(props.shift.ends_at, new Date()),
);

// The seat an Officer is correcting (#450) — a schedule admin's pencil, no time bound, on any
// seat. Null unless the Officer has opened a correction; it takes precedence over the own-seat
// window below, so one form is ever open at a time.
const correctingSeat = ref<ShiftSignUp | null>(null);

// GDR's five origin fields, in the order the sign-out panel lists them.
const provenanceFields = [
    { key: 'visitors_france_europe', labelKey: 'group.scheduling_panel.agenda.sign_out.provenance_france_europe' },
    { key: 'visitors_quebec', labelKey: 'group.scheduling_panel.agenda.sign_out.provenance_quebec' },
    { key: 'visitors_toronto', labelKey: 'group.scheduling_panel.agenda.sign_out.provenance_toronto' },
    { key: 'visitors_rest_of_canada', labelKey: 'group.scheduling_panel.agenda.sign_out.provenance_rest_of_canada' },
    { key: 'visitors_other_countries', labelKey: 'group.scheduling_panel.agenda.sign_out.provenance_other_countries' },
] as const;

// The five origins already recorded on a seat (own or a corrected one), null-safe, so the form
// pre-fills a correction rather than making anyone retype.
const provenanceOf = (source: ShiftAgendaItem | ShiftSignUp): VisitorProvenance => ({
    visitors_france_europe: source.visitors_france_europe ?? null,
    visitors_quebec: source.visitors_quebec ?? null,
    visitors_toronto: source.visitors_toronto ?? null,
    visitors_rest_of_canada: source.visitors_rest_of_canada ?? null,
    visitors_other_countries: source.visitors_other_countries ?? null,
});

type RecordTarget = { signUpId: number; visitor_count: number | null; extra_interaction_count: number | null } & VisitorProvenance;

// The seat the form is writing, normalised to one shape. An Officer's chosen seat wins; otherwise
// the viewer's own seat inside the sign-out window. Null → no form. The write always names this
// seat's id, so an Officer's correction and a self sign-out post through the one PATCH seam.
const recordTarget = computed<RecordTarget | null>(() => {
    const seat = correctingSeat.value;
    if (seat && seat.signup_id !== undefined) {
        return {
            signUpId: seat.signup_id,
            visitor_count: seat.visitor_count ?? null,
            extra_interaction_count: seat.extra_interaction_count ?? null,
            ...provenanceOf(seat),
        };
    }

    if (showSignOut.value && props.shift.signup_id !== null) {
        return {
            signUpId: props.shift.signup_id,
            visitor_count: props.shift.visitor_count,
            extra_interaction_count: props.shift.extra_interaction_count,
            ...provenanceOf(props.shift),
        };
    }

    return null;
});

// The boxes, seeded from whichever seat the form now targets so a correction edits rather than
// retypes. The count box stays required (the Sign Out button waits for it — the forcing function
// that carries the count on 96-98% of shifts); the extra box is optional; the five origins are
// seeded too. A box holds a string until the user types, then a number (#646); an empty box stays
// distinct from a typed zero.
const draft = ref<BoxValue>('');
const extraDraft = ref<BoxValue>('');
const provenanceDrafts = ref(Object.fromEntries(provenanceFields.map((f) => [f.key, ''])) as Record<keyof VisitorProvenance, BoxValue>);

watch(
    recordTarget,
    (target) => {
        draft.value = target && target.visitor_count !== null ? String(target.visitor_count) : '';
        extraDraft.value = target && target.extra_interaction_count !== null ? String(target.extra_interaction_count) : '';
        provenanceFields.forEach((field) => {
            const value = target ? target[field.key] : null;
            provenanceDrafts.value[field.key] = value !== null ? String(value) : '';
        });
    },
    { immediate: true },
);

const recordDraft = computed(() => ({ count: draft.value, extra: extraDraft.value, provenance: provenanceDrafts.value }));

const recordFlags = computed(() => ({
    collectsExtraInteractions: props.collectsExtraInteractions,
    collectsVisitorProvenance: props.collectsVisitorProvenance,
}));

// The count is required; on GDR the five origins must be filled and add up to it.
const canSubmit = computed(() => canSubmitRecord(recordDraft.value, recordFlags.value));

// Open the pencil on a seat (#450) — a schedule admin corrects any seat; `can_record` gates the
// affordance and the SignUpPolicy re-checks the write. Cancel closes it and returns the form to
// the own-seat window if one is open.
const openCorrection = (signUp: ShiftSignUp) => {
    correctingSeat.value = signUp;
};

const cancelCorrection = () => {
    correctingSeat.value = null;
};

const submitRecord = () => {
    if (!canSubmit.value || recordTarget.value === null) return;

    emit('record', { signUpId: recordTarget.value.signUpId, ...buildRecordPayload(recordDraft.value, recordFlags.value) });
    correctingSeat.value = null;
};

// The recorded count and extra shown on a seat's chip: an Officer reads every seat's own numbers
// (#450), everyone else only their own (#445). Null shows nothing; a recorded zero shows "0".
const seatCount = (signUp: ShiftSignUp): number | null =>
    signUp.can_record ? (signUp.visitor_count ?? null) : isOwnSeat(signUp) ? props.shift.visitor_count : null;

const seatExtra = (signUp: ShiftSignUp): number | null =>
    signUp.can_record ? (signUp.extra_interaction_count ?? null) : isOwnSeat(signUp) ? props.shift.extra_interaction_count : null;
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
                </div>
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('group.scheduling_panel.agenda.seats', { taken: String(shift.taken), capacity: String(shift.capacity) }) }}
                </span>
            </div>

            <!-- Who is on the floor (#357) — visible to every reader who can read the
                 Schedule, non-members included. Each seat is a chip; a schedule admin gets a
                 remove (×) on every seat (officer removal, #359). An honest empty line otherwise. -->
            <div v-if="shift.signups.length" class="flex flex-wrap items-center gap-1.5">
                <span class="text-muted-foreground text-sm font-medium">{{ trans('group.scheduling_panel.agenda.sign_up.signed_up_label') }}:</span>
                <Badge v-for="signUp in shift.signups" :key="signUp.id" variant="secondary" class="gap-1 font-normal">
                    {{ signUpName(signUp) }}
                    <!-- The Objects on this seat (#586, ADR-0026 §3) — what the Member is taking
                         onto the floor, named under them so a colleague sees what is already out
                         before they pick. A retired Object still shows its name here. -->
                    <span v-if="signUp.objects && signUp.objects.length" class="text-muted-foreground">
                        · {{ signUp.objects.map((object) => object.name).join(', ') }}
                    </span>
                    <!-- The recorded numbers read on the chip: the viewer's own seat (#445), and
                         every seat for an Officer (#450). Null (no value yet) shows nothing; a
                         recorded zero shows "0 visitors". The tour-leading extra count reads
                         beside it where both are recorded (#447). -->
                    <span v-if="seatCount(signUp) !== null" class="text-muted-foreground tabular-nums">
                        · {{ trans('group.scheduling_panel.agenda.sign_out.recorded', { count: String(seatCount(signUp)) }) }}
                    </span>
                    <span v-if="seatExtra(signUp) !== null" class="text-muted-foreground tabular-nums">
                        · {{ trans('group.scheduling_panel.agenda.sign_out.extra_recorded', { count: String(seatExtra(signUp)) }) }}
                    </span>
                    <!-- The Officer's pencil (#450) — corrects any seat, no deadline. Shown only
                         where the server sent `can_record` (the schedule-admin gate); an ordinary
                         Member sees no pencil on anyone's seat, and the SignUpPolicy refuses the
                         write regardless. -->
                    <button
                        v-if="signUp.can_record"
                        type="button"
                        class="hover:text-rom-ink -mr-0.5 rounded-full transition-colors"
                        :aria-label="trans('group.scheduling_panel.agenda.sign_out.correct')"
                        @click="openCorrection(signUp)"
                    >
                        <PhPencilSimple class="size-3" />
                    </button>
                    <button
                        v-if="signUp.signup_id"
                        type="button"
                        class="hover:text-destructive -mr-0.5 rounded-full transition-colors"
                        :aria-label="trans('group.scheduling_panel.agenda.assign.remove')"
                        @click="emit('remove', signUp.signup_id)"
                    >
                        <PhX class="size-3" />
                    </button>
                </Badge>
            </div>
            <p v-else class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.agenda.sign_up.nobody') }}</p>

            <!-- Take / drop, from the same place. `signup_id` means "I hold a seat";
                 `can.signUp` means "a free seat is offered to me". A full Shift the viewer has
                 no seat on shows as full with neither button. Self-service closes once the Shift
                 has started (#554), so take, drop, and the full label all hide then; the Officer's
                 assign (#359) and the sign-out box below stay. The Scheduler's assign sits beside
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
                <!-- The self-serve owner's Edit / Delete (#585, ADR-0026 §1) — shown to the Member
                     who wrote the Shift, until it starts (`can.manageSelfServe`). They route through
                     the self-serve seam, so they are their own emits, not the Scheduler's above; a
                     Member never sees both, since `can.update` needs the schedule-admin gate. -->
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

            <!-- Recording the numbers (#445, #450, ADR-0023 §5) — one form, two ways in: the
                 seat-holder's own sign-out from five minutes before the Shift ends, and the
                 Officer's correction of any seat with no deadline. One box and a submit button,
                 disabled until a number is typed (the forcing function). The server requires,
                 whole-checks, bounds and re-authorises every write regardless. -->
            <form v-if="recordTarget" class="flex flex-wrap items-end gap-2 border-t pt-3" @submit.prevent="submitRecord">
                <!-- When an Officer is correcting a seat, name whose seat it is, so the correction
                     is never mistaken for a self sign-out. -->
                <p v-if="correctingSeat" class="text-muted-foreground w-full text-sm font-medium">
                    {{ trans('group.scheduling_panel.agenda.sign_out.correcting', { name: signUpName(correctingSeat) }) }}
                </p>
                <label class="flex flex-col gap-1">
                    <span class="text-muted-foreground text-sm font-medium">{{ trans('group.scheduling_panel.agenda.sign_out.count_label') }}</span>
                    <Input
                        v-model="draft"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        step="1"
                        class="w-40"
                        :placeholder="trans('group.scheduling_panel.agenda.sign_out.placeholder')"
                    />
                </label>
                <!-- The tour-leading second box (#447, ADR-0023 §2) — visitors served outside the
                     tour. Optional: it never gates the Sign Out button below. Shown only where the
                     Group collects the split; a count-only Group renders one box. -->
                <label v-if="collectsExtraInteractions" class="flex flex-col gap-1">
                    <span class="text-muted-foreground text-sm font-medium">{{ trans('group.scheduling_panel.agenda.sign_out.extra_label') }}</span>
                    <Input
                        v-model="extraDraft"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        step="1"
                        class="w-40"
                        :placeholder="trans('group.scheduling_panel.agenda.sign_out.extra_placeholder')"
                    />
                </label>
                <!-- GDR's five visitor origins (#448, ADR-0023 §3) — where the tour's visitors came
                     from, one box each. Shown only where the Group collects provenance; the five are
                     required together and must sum to the count, which the button below enforces as
                     the client's copy of the server rule. -->
                <template v-if="collectsVisitorProvenance">
                    <label v-for="field in provenanceFields" :key="field.key" class="flex flex-col gap-1">
                        <span class="text-muted-foreground text-sm font-medium">{{ trans(field.labelKey) }}</span>
                        <Input v-model="provenanceDrafts[field.key]" type="number" inputmode="numeric" min="0" step="1" class="w-40" />
                    </label>
                </template>
                <Button type="submit" size="sm" :disabled="!canSubmit">
                    {{ trans(correctingSeat ? 'group.scheduling_panel.agenda.sign_out.save' : 'group.scheduling_panel.agenda.sign_out.submit') }}
                </Button>
                <!-- An Officer's correction can be closed without writing; the own-seat sign-out
                     has no cancel — it is simply the window being open. -->
                <Button v-if="correctingSeat" type="button" variant="ghost" size="sm" @click="cancelCorrection">
                    {{ trans('group.scheduling_panel.agenda.sign_out.cancel') }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
