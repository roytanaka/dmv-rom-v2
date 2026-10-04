<script setup lang="ts">
// Group Settings tab (#604, ADR-0027) — one page of cards holding the Group's settings: values
// an officer sets once and the app reads on every later action. Records (Shifts, Meetings,
// roster lines) stay inline on the section where they are read (§2). The tab renders only to a
// viewer holding a configuration right; each card renders only behind its own `can` hint, and
// a scheduling card only while the Group runs scheduling. The server re-checks every save.
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import { PhArrowDown, PhArrowUp } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

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
    // The Group's shift kinds (#567) — the full list in picker order, retired kinds included.
    // Resolved only with `canManageShiftKinds`.
    shiftKinds: { id: number; name: string; active: boolean; offSite: boolean; sortOrder: number }[] | null;
    canManageShiftKinds: boolean;
    // The Group's Objects (#584) — the full handling collection in picker order, retired Objects
    // included. Resolved only with `canManageObjects`.
    objects: { id: number; name: string; active: boolean; sortOrder: number }[] | null;
    canManageObjects: boolean;
    groupSlug: string;
}>();

const showReminders = computed(() => props.canManageReminders && props.runsScheduling && props.reminders !== null);
const showEmptyDesk = computed(() => props.canManageEmptyDesk && props.runsScheduling && props.emptyDesk !== null);
const showSelfServe = computed(() => props.canManageSelfServe && props.runsScheduling && props.selfServe !== null);
const showShiftKinds = computed(() => props.canManageShiftKinds && props.runsScheduling && props.shiftKinds !== null);
const showObjects = computed(() => props.canManageObjects && props.runsScheduling && props.objects !== null);
const showAnyCard = computed(() => showReminders.value || showEmptyDesk.value || showSelfServe.value || showShiftKinds.value || showObjects.value);

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

// --- Shift-kind maintenance (#567, ADR-0021 §3) — the schedule-admin adds, renames, retires,
// reinstates and reorders the Group's kinds, gated by `canManageShiftKinds`. There is no delete.
// Each action hits its own endpoint and the server re-checks the gate; the page reloads with the
// fresh list, so no local list state is kept.

// Add a kind: name only. A new kind lands at the end of the order and is active.
const addKindForm = useForm<{ name: string }>({ name: '' });
const addKind = () =>
    addKindForm.post(route('groups.shift-kinds.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => addKindForm.reset('name'),
    });

// The name being edited inline, keyed by kind id, with its own error slot for a rejected rename.
const kindNameDrafts = ref<Record<number, string>>({});
const renameError = ref<string | undefined>(undefined);

const startRename = (id: number, name: string) => {
    renameError.value = undefined;
    kindNameDrafts.value = { ...kindNameDrafts.value, [id]: name };
};

const cancelRename = (id: number) => {
    const rest = { ...kindNameDrafts.value };
    delete rest[id];
    kindNameDrafts.value = rest;
};

const saveRename = (id: number) => {
    router.patch(
        route('shift-kinds.update', { shiftKind: id }),
        { name: kindNameDrafts.value[id] },
        {
            preserveScroll: true,
            onSuccess: () => cancelRename(id),
            onError: (errors) => (renameError.value = errors.name),
        },
    );
};

// Retire (active → false) or reinstate (false → true). One PATCH carrying the flag.
const setKindActive = (id: number, active: boolean) =>
    router.patch(route('shift-kinds.update', { shiftKind: id }), { active }, { preserveScroll: true });

// Set or clear the off-site flag (#587, ADR-0026 §4) — it widens the kind's Object hold a day
// either side. One PATCH carrying the flag, the same seam as retire / reinstate.
const setKindOffSite = (id: number, offSite: boolean) =>
    router.patch(route('shift-kinds.update', { shiftKind: id }), { off_site: offSite }, { preserveScroll: true });

// Reorder by swapping a kind with its neighbour, then sending the whole id list in the new order.
const moveKind = (index: number, delta: number) => {
    const ids = (props.shiftKinds ?? []).map((kind) => kind.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) {
        return;
    }
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.patch(route('groups.shift-kinds.reorder', { group: props.groupSlug }), { ids }, { preserveScroll: true });
};

// --- Objects maintenance (#584, ADR-0026 §3) — the schedule-admin adds, renames, retires,
// reinstates and reorders the Group's handling collection, gated by `canManageObjects`. The same
// shape as shift-kind maintenance: each action hits its own endpoint, the server re-checks the
// gate, and the page reloads with the fresh list, so no local list state is kept.

// Add an Object: name only. A new Object lands at the end of the order and is active.
const addObjectForm = useForm<{ name: string }>({ name: '' });
const addObject = () =>
    addObjectForm.post(route('groups.objects.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => addObjectForm.reset('name'),
    });

// The name being edited inline, keyed by Object id, with its own error slot for a rejected rename.
const objectNameDrafts = ref<Record<number, string>>({});
const objectRenameError = ref<string | undefined>(undefined);

const startObjectRename = (id: number, name: string) => {
    objectRenameError.value = undefined;
    objectNameDrafts.value = { ...objectNameDrafts.value, [id]: name };
};

const cancelObjectRename = (id: number) => {
    const rest = { ...objectNameDrafts.value };
    delete rest[id];
    objectNameDrafts.value = rest;
};

const saveObjectRename = (id: number) => {
    router.patch(
        route('objects.update', { object: id }),
        { name: objectNameDrafts.value[id] },
        {
            preserveScroll: true,
            onSuccess: () => cancelObjectRename(id),
            onError: (errors) => (objectRenameError.value = errors.name),
        },
    );
};

// Retire (active → false) or reinstate (false → true). One PATCH carrying the flag.
const setObjectActive = (id: number, active: boolean) => router.patch(route('objects.update', { object: id }), { active }, { preserveScroll: true });

// Reorder by swapping an Object with its neighbour, then sending the whole id list in the new order.
const moveObject = (index: number, delta: number) => {
    const ids = (props.objects ?? []).map((object) => object.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) {
        return;
    }
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.patch(route('groups.objects.reorder', { group: props.groupSlug }), { ids }, { preserveScroll: true });
};
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

            <!-- Shift-kind maintenance (#567, ADR-0021 §3) — the schedule-admin adds, renames, retires,
                 reinstates and reorders the Group's kinds. Shown only to a Scheduler / Chair
                 (`canManageShiftKinds`) of a scheduling Group; the server re-checks on every write. There
                 is no delete: a kind is retired, not removed, so its old Shifts keep their name. The
                 `shiftKinds` test narrows the prop's type for the rows below. -->
            <Card v-if="showShiftKinds && shiftKinds">
                <CardHeader>
                    <CardTitle>{{ trans('group.scheduling_panel.shift_kinds.heading') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.shift_kinds.description') }}</p>

                    <p v-if="shiftKinds.length === 0" class="text-muted-foreground text-sm">
                        {{ trans('group.scheduling_panel.shift_kinds.empty') }}
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="(kind, index) in shiftKinds" :key="kind.id" class="flex flex-wrap items-center gap-2">
                            <div class="flex flex-col">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="index === 0"
                                    :aria-label="trans('group.scheduling_panel.shift_kinds.move_up')"
                                    @click="moveKind(index, -1)"
                                >
                                    <PhArrowUp class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="index === shiftKinds.length - 1"
                                    :aria-label="trans('group.scheduling_panel.shift_kinds.move_down')"
                                    @click="moveKind(index, 1)"
                                >
                                    <PhArrowDown class="size-4" />
                                </Button>
                            </div>

                            <!-- Inline rename: the name shows as text until the admin edits it. -->
                            <template v-if="kindNameDrafts[kind.id] !== undefined">
                                <Input v-model="kindNameDrafts[kind.id]" class="h-8 w-48" @keyup.enter="saveRename(kind.id)" />
                                <Button type="button" size="sm" @click="saveRename(kind.id)">
                                    {{ trans('group.scheduling_panel.save') }}
                                </Button>
                                <Button type="button" size="sm" variant="ghost" @click="cancelRename(kind.id)">
                                    {{ trans('group.scheduling_panel.cancel') }}
                                </Button>
                            </template>
                            <template v-else>
                                <span class="text-sm" :class="{ 'text-muted-foreground line-through': !kind.active }">{{ kind.name }}</span>
                                <Badge v-if="!kind.active" variant="secondary">{{ trans('group.scheduling_panel.shift_kinds.retired_badge') }}</Badge>
                                <Button type="button" size="sm" variant="ghost" @click="startRename(kind.id, kind.name)">
                                    {{ trans('group.scheduling_panel.shift_kinds.rename') }}
                                </Button>
                                <Button v-if="kind.active" type="button" size="sm" variant="ghost" @click="setKindActive(kind.id, false)">
                                    {{ trans('group.scheduling_panel.shift_kinds.retire') }}
                                </Button>
                                <Button v-else type="button" size="sm" variant="ghost" @click="setKindActive(kind.id, true)">
                                    {{ trans('group.scheduling_panel.shift_kinds.reinstate') }}
                                </Button>
                                <!-- Off-site (#587, ADR-0026 §4): flag an event station so its Objects are held a day
                                     either side. -->
                                <label class="text-muted-foreground ml-auto flex items-center gap-1.5 text-sm">
                                    <Checkbox :checked="kind.offSite" @update:checked="(on: boolean) => setKindOffSite(kind.id, on)" />
                                    {{ trans('group.scheduling_panel.shift_kinds.off_site') }}
                                </label>
                            </template>
                        </li>
                    </ul>
                    <InputError :message="renameError" />

                    <!-- Add a kind: name only. -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="new-shift-kind">{{ trans('group.scheduling_panel.shift_kinds.add_label') }}</Label>
                        <div class="flex flex-wrap items-start gap-2">
                            <Input id="new-shift-kind" v-model="addKindForm.name" class="h-8 w-48" @keyup.enter="addKind" />
                            <Button type="button" size="sm" :disabled="addKindForm.processing" @click="addKind">
                                {{ trans('group.scheduling_panel.shift_kinds.add') }}
                            </Button>
                        </div>
                        <InputError :message="addKindForm.errors.name" />
                    </div>
                </CardContent>
            </Card>

            <!-- Objects maintenance (#584, ADR-0026 §3) — the schedule-admin adds, renames, retires,
                 reinstates and reorders the Group's handling collection. Shown only to a Scheduler /
                 Chair (`canManageObjects`) of a scheduling Group; the server re-checks on every write.
                 There is no delete: an Object is retired, not removed, so its old Sign-ups keep their
                 name. The `objects` test narrows the prop's type for the rows below. -->
            <Card v-if="showObjects && objects">
                <CardHeader>
                    <CardTitle>{{ trans('group.scheduling_panel.objects.heading') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.objects.description') }}</p>

                    <p v-if="objects.length === 0" class="text-muted-foreground text-sm">
                        {{ trans('group.scheduling_panel.objects.empty') }}
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="(object, index) in objects" :key="object.id" class="flex flex-wrap items-center gap-2">
                            <div class="flex flex-col">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="index === 0"
                                    :aria-label="trans('group.scheduling_panel.objects.move_up')"
                                    @click="moveObject(index, -1)"
                                >
                                    <PhArrowUp class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="index === objects.length - 1"
                                    :aria-label="trans('group.scheduling_panel.objects.move_down')"
                                    @click="moveObject(index, 1)"
                                >
                                    <PhArrowDown class="size-4" />
                                </Button>
                            </div>

                            <!-- Inline rename: the name shows as text until the admin edits it. -->
                            <template v-if="objectNameDrafts[object.id] !== undefined">
                                <Input v-model="objectNameDrafts[object.id]" class="h-8 w-48" @keyup.enter="saveObjectRename(object.id)" />
                                <Button type="button" size="sm" @click="saveObjectRename(object.id)">
                                    {{ trans('group.scheduling_panel.save') }}
                                </Button>
                                <Button type="button" size="sm" variant="ghost" @click="cancelObjectRename(object.id)">
                                    {{ trans('group.scheduling_panel.cancel') }}
                                </Button>
                            </template>
                            <template v-else>
                                <span class="text-sm" :class="{ 'text-muted-foreground line-through': !object.active }">{{ object.name }}</span>
                                <Badge v-if="!object.active" variant="secondary">{{ trans('group.scheduling_panel.objects.retired_badge') }}</Badge>
                                <Button type="button" size="sm" variant="ghost" @click="startObjectRename(object.id, object.name)">
                                    {{ trans('group.scheduling_panel.objects.rename') }}
                                </Button>
                                <Button v-if="object.active" type="button" size="sm" variant="ghost" @click="setObjectActive(object.id, false)">
                                    {{ trans('group.scheduling_panel.objects.retire') }}
                                </Button>
                                <Button v-else type="button" size="sm" variant="ghost" @click="setObjectActive(object.id, true)">
                                    {{ trans('group.scheduling_panel.objects.reinstate') }}
                                </Button>
                            </template>
                        </li>
                    </ul>
                    <InputError :message="objectRenameError" />

                    <!-- Add an Object: name only. -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="new-object">{{ trans('group.scheduling_panel.objects.add_label') }}</Label>
                        <div class="flex flex-wrap items-start gap-2">
                            <Input id="new-object" v-model="addObjectForm.name" class="h-8 w-48" @keyup.enter="addObject" />
                            <Button type="button" size="sm" :disabled="addObjectForm.processing" @click="addObject">
                                {{ trans('group.scheduling_panel.objects.add') }}
                            </Button>
                        </div>
                        <InputError :message="addObjectForm.errors.name" />
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- The tab appears with authority, not with data (ADR-0027 §1): a viewer whose rights
             reach no card here still sees the tab, and this line instead of a blank page. -->
        <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.settings_empty') }}</p>
    </div>
</template>
