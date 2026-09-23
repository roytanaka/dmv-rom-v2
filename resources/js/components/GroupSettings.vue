<script setup lang="ts">
// Group Settings tab (#604, ADR-0027) — one page of cards holding the Group's settings: values
// an officer sets once and the app reads on every later action. Records (Shifts, Meetings,
// roster lines) stay inline on the section where they are read (§2). The tab renders only to a
// viewer holding a configuration right; each card renders only behind its own `can` hint, and
// a scheduling card only while the Group runs scheduling. The server re-checks every save.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    // Whether the Group runs scheduling — the scheduling cards render only while it does.
    runsScheduling: boolean;
    // The Group's Reminder settings (#486), resolved only with `canManageReminders`.
    reminders: { enabled: boolean; leadDays: number } | null;
    canManageReminders: boolean;
    // The Group's empty-desk settings (#487), its watch-tick rows drawn from the Group's shift
    // kinds. Resolved only with `canManageEmptyDesk`.
    emptyDesk: { enabled: boolean; daysAhead: number; shiftKinds: { id: number; name: string; watched: boolean }[] } | null;
    canManageEmptyDesk: boolean;
    // The Group's self-serve settings (#582), resolved only with `canManageSelfServe`.
    selfServe: { enabled: boolean; unitMinutes: number } | null;
    canManageSelfServe: boolean;
    groupSlug: string;
}>();

const showReminders = computed(() => props.canManageReminders && props.runsScheduling && props.reminders !== null);
const showEmptyDesk = computed(() => props.canManageEmptyDesk && props.runsScheduling && props.emptyDesk !== null);
const showSelfServe = computed(() => props.canManageSelfServe && props.runsScheduling && props.selfServe !== null);
const showAnyCard = computed(() => showReminders.value || showEmptyDesk.value || showSelfServe.value);

// --- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and lead
// days, gated by `canManageReminders`. One PATCH to the dedicated endpoint; the server
// re-checks the gate. The form seeds from the Group's current settings.
const reminderForm = useForm<{ reminders_enabled: boolean; reminder_lead_days: number }>({
    reminders_enabled: props.reminders?.enabled ?? false,
    reminder_lead_days: props.reminders?.leadDays ?? 0,
});

const saveReminders = () => reminderForm.patch(route('groups.reminders.update', { group: props.groupSlug }), { preserveScroll: true });

// --- Empty-desk settings (#487, ADR-0024 §7) — the schedule-admin's on/off switch, look-ahead,
// and the tick-rows marking which shift kinds to watch, gated by `canManageEmptyDesk`. One PATCH
// to the dedicated endpoint; the server re-checks the gate. The form seeds from the Group's
// current settings, its watched-kinds set drawn from the kinds already flagged.
const emptyDeskForm = useForm<{ empty_desk_alert_enabled: boolean; empty_desk_days_ahead: number; watched_shift_kinds: number[] }>({
    empty_desk_alert_enabled: props.emptyDesk?.enabled ?? false,
    empty_desk_days_ahead: props.emptyDesk?.daysAhead ?? 0,
    watched_shift_kinds: props.emptyDesk?.shiftKinds.filter((kind) => kind.watched).map((kind) => kind.id) ?? [],
});

const toggleWatchedKind = (id: number, on: boolean) => {
    emptyDeskForm.watched_shift_kinds = on
        ? [...emptyDeskForm.watched_shift_kinds, id]
        : emptyDeskForm.watched_shift_kinds.filter((kindId) => kindId !== id);
};

const saveEmptyDesk = () => emptyDeskForm.patch(route('groups.empty-desk.update', { group: props.groupSlug }), { preserveScroll: true });

// --- Self-serve settings (#582, ADR-0026 §1 and §2) — the schedule-admin's self-serve on/off
// switch and the unit length in minutes, gated by `canManageSelfServe`. One PATCH to the dedicated
// endpoint; the server re-checks the gate. The form seeds from the Group's current settings.
const selfServeForm = useForm<{ self_serve_shifts: boolean; self_serve_unit_minutes: number }>({
    self_serve_shifts: props.selfServe?.enabled ?? false,
    self_serve_unit_minutes: props.selfServe?.unitMinutes ?? 0,
});

const saveSelfServe = () => selfServeForm.patch(route('groups.self-serve.update', { group: props.groupSlug }), { preserveScroll: true });
</script>

<template>
    <div class="flex flex-col gap-4">
        <template v-if="showAnyCard">
            <!-- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and
                 lead days for this Group's shift Reminders. Shown only to a Scheduler / Chair
                 (`canManageReminders`) of a scheduling Group; the server re-checks on save. -->
            <Card v-if="showReminders">
                <CardHeader>
                    <CardTitle>{{ trans('group.scheduling_panel.reminders.heading') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.reminders.description') }}</p>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :checked="reminderForm.reminders_enabled"
                            @update:checked="(on: boolean) => (reminderForm.reminders_enabled = on)"
                        />
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
                 look-ahead, and the tick-rows marking which shift kinds the alert watches. Shown
                 only to a Scheduler / Chair (`canManageEmptyDesk`) of a scheduling Group; the
                 server re-checks on save. The `emptyDesk` test narrows the prop's type for the
                 tick-rows below. -->
            <Card v-if="showEmptyDesk && emptyDesk">
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

            <!-- Self-serve settings (#582, ADR-0026 §1 and §2) — the schedule-admin's self-serve
                 on/off switch and the unit length in minutes. Shown only to a Scheduler / Chair
                 (`canManageSelfServe`) of a scheduling Group; the server re-checks on save. -->
            <Card v-if="showSelfServe">
                <CardHeader>
                    <CardTitle>{{ trans('group.scheduling_panel.self_serve.heading') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.self_serve.description') }}</p>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :checked="selfServeForm.self_serve_shifts"
                            @update:checked="(on: boolean) => (selfServeForm.self_serve_shifts = on)"
                        />
                        {{ trans('group.scheduling_panel.self_serve.enabled_label') }}
                    </label>
                    <div class="flex flex-col gap-1.5">
                        <Label for="self-serve-unit-minutes">{{ trans('group.scheduling_panel.self_serve.unit_minutes_label') }}</Label>
                        <Input
                            id="self-serve-unit-minutes"
                            v-model.number="selfServeForm.self_serve_unit_minutes"
                            type="number"
                            min="15"
                            max="240"
                            class="w-24"
                        />
                        <InputError :message="selfServeForm.errors.self_serve_unit_minutes" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="button" size="sm" :disabled="selfServeForm.processing" @click="saveSelfServe">
                            {{ trans('group.scheduling_panel.save') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- The tab appears with authority, not with data (ADR-0027 §1): a viewer whose rights
             reach no card here still sees the tab, and this line instead of a blank page. -->
        <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.settings_empty') }}</p>
    </div>
</template>
