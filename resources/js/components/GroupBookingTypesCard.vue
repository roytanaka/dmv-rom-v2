<script setup lang="ts">
// The Settings tab's Group tours card (#794, ADR-0032 §1, §4, §6) — a Booker or Chair keeps the
// Group's booking types (add, edit name and rates, retire, restore, reorder, delete one added in
// error) and sets the group-tour shift kind and Schedule label. Each action hits its own endpoint
// and the server re-checks the BookingTypePolicy; the page reloads with the fresh list, so no local
// list state is kept. Type names and the label are content, shown as authored (ADR-0004).
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { router, useForm } from '@inertiajs/vue3';
import { PhArrowDown, PhArrowUp } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

export type SettingsBookingType = {
    id: number;
    name: string;
    ratePerVisitor: string;
    ratePerDocentHour: string;
    active: boolean;
    sortOrder: number;
};

export type SettingsBookings = {
    types: SettingsBookingType[];
    shiftKindId: number | null;
    label: string | null;
    copyEmail: string | null;
    shiftKinds: { id: number; name: string; active: boolean }[];
};

const props = defineProps<{
    bookings: SettingsBookings;
    groupSlug: string;
}>();

// The group-tour settings: the kind a Booking's Shift takes, the Schedule label and the copy
// address every Booking mail is copied to (#799), saved together. A blank address clears it.
const settingsForm = useForm<{ group_tour_shift_kind_id: number | null; group_tour_label: string; booking_copy_email: string }>({
    group_tour_shift_kind_id: props.bookings.shiftKindId,
    group_tour_label: props.bookings.label ?? '',
    booking_copy_email: props.bookings.copyEmail ?? '',
});

const saveSettings = () => settingsForm.patch(route('groups.group-tours.update', { group: props.groupSlug }), { preserveScroll: true });

// One dialog adds a type or edits one: name and both rates.
const editing = ref<SettingsBookingType | null>(null);
const dialogOpen = ref(false);
const typeForm = useForm<{ name: string; rate_per_visitor: string; rate_per_docent_hour: string }>({
    name: '',
    rate_per_visitor: '0',
    rate_per_docent_hour: '0',
});

const openAdd = () => {
    typeForm.clearErrors();
    typeForm.name = '';
    typeForm.rate_per_visitor = '0';
    typeForm.rate_per_docent_hour = '0';
    editing.value = null;
    dialogOpen.value = true;
};

const openEdit = (type: SettingsBookingType) => {
    typeForm.clearErrors();
    typeForm.name = type.name;
    typeForm.rate_per_visitor = type.ratePerVisitor;
    typeForm.rate_per_docent_hour = type.ratePerDocentHour;
    editing.value = type;
    dialogOpen.value = true;
};

const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };
    if (editing.value) {
        typeForm.patch(route('booking-types.update', { bookingType: editing.value.id }), options);
    } else {
        typeForm.post(route('groups.booking-types.store', { group: props.groupSlug }), options);
    }
};

const setActive = (id: number, active: boolean) =>
    router.patch(route('booking-types.update', { bookingType: id }), { active }, { preserveScroll: true });

// Reorder by swapping a type with its neighbour, then sending the whole id list in the new order.
const moveType = (index: number, delta: number) => {
    const ids = props.bookings.types.map((type) => type.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) {
        return;
    }
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.patch(route('groups.booking-types.reorder', { group: props.groupSlug }), { ids }, { preserveScroll: true });
};

// Delete, behind a confirm. The server refuses while any Booking uses the type.
const deleting = ref<SettingsBookingType | null>(null);
const rowError = ref<string | undefined>(undefined);

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }
    rowError.value = undefined;
    router.delete(route('booking-types.destroy', { bookingType: deleting.value.id }), {
        preserveScroll: true,
        onError: (errors) => (rowError.value = errors.booking_type),
        onFinish: () => (deleting.value = null),
    });
};

const money = (value: string) => `$${value}`;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ trans('group.booking_types.heading') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-6">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="group-tour-kind">{{ trans('group.booking_types.shift_kind_label') }}</Label>
                    <NativeSelect id="group-tour-kind" v-model="settingsForm.group_tour_shift_kind_id" class="sm:w-80">
                        <option :value="null">{{ trans('group.booking_types.none') }}</option>
                        <option v-for="kind in bookings.shiftKinds" :key="kind.id" :value="kind.id">{{ kind.name }}</option>
                    </NativeSelect>
                    <InputError :message="settingsForm.errors.group_tour_shift_kind_id" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="group-tour-label">{{ trans('group.booking_types.label_label') }}</Label>
                    <Input id="group-tour-label" v-model="settingsForm.group_tour_label" class="sm:w-80" />
                    <InputError :message="settingsForm.errors.group_tour_label" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="group-tour-copy-email">{{ trans('group.booking_mails.copy_email_label') }}</Label>
                    <Input id="group-tour-copy-email" v-model="settingsForm.booking_copy_email" type="email" class="sm:w-80" />
                    <InputError :message="settingsForm.errors.booking_copy_email" />
                </div>
                <div class="flex justify-end">
                    <Button type="button" size="sm" :disabled="settingsForm.processing" @click="saveSettings">
                        {{ trans('group.booking_types.save') }}
                    </Button>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <h3 class="text-sm font-medium">{{ trans('group.booking_types.types_heading') }}</h3>
                <p v-if="bookings.types.length === 0" class="text-muted-foreground text-sm">{{ trans('group.booking_types.empty') }}</p>
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="(type, index) in bookings.types" :key="type.id" class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-col">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :disabled="index === 0"
                                :aria-label="trans('group.booking_types.move_up')"
                                @click="moveType(index, -1)"
                            >
                                <PhArrowUp class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :disabled="index === bookings.types.length - 1"
                                :aria-label="trans('group.booking_types.move_down')"
                                @click="moveType(index, 1)"
                            >
                                <PhArrowDown class="size-4" />
                            </Button>
                        </div>
                        <div class="flex min-w-0 flex-col">
                            <span class="text-sm" :class="{ 'text-muted-foreground line-through': !type.active }">{{ type.name }}</span>
                            <span class="text-muted-foreground text-xs">
                                {{ trans('group.booking_types.rates', { visitor: money(type.ratePerVisitor), hour: money(type.ratePerDocentHour) }) }}
                            </span>
                        </div>
                        <Badge v-if="!type.active" variant="secondary">{{ trans('group.booking_types.retired_badge') }}</Badge>
                        <div class="ml-auto flex flex-wrap gap-1">
                            <Button type="button" size="sm" variant="ghost" @click="openEdit(type)">{{ trans('group.booking_types.edit') }}</Button>
                            <Button v-if="type.active" type="button" size="sm" variant="ghost" @click="setActive(type.id, false)">
                                {{ trans('group.booking_types.retire') }}
                            </Button>
                            <Button v-else type="button" size="sm" variant="ghost" @click="setActive(type.id, true)">
                                {{ trans('group.booking_types.restore') }}
                            </Button>
                            <Button type="button" size="sm" variant="ghost" @click="deleting = type">{{
                                trans('group.booking_types.delete')
                            }}</Button>
                        </div>
                    </li>
                </ul>
                <InputError :message="rowError" />
                <div>
                    <Button type="button" size="sm" variant="outline" @click="openAdd">{{ trans('group.booking_types.add') }}</Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(open: boolean) => (dialogOpen = open)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? trans('group.booking_types.edit_title', { type: editing.name }) : trans('group.booking_types.add_title') }}
                </DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveType">
                <div class="flex flex-col gap-1.5">
                    <Label for="booking-type-name">{{ trans('group.booking_types.name') }}</Label>
                    <Input id="booking-type-name" v-model="typeForm.name" />
                    <InputError :message="typeForm.errors.name" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="booking-type-visitor">{{ trans('group.booking_types.rate_per_visitor') }}</Label>
                    <Input id="booking-type-visitor" v-model="typeForm.rate_per_visitor" type="number" inputmode="decimal" min="0" step="0.01" />
                    <InputError :message="typeForm.errors.rate_per_visitor" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="booking-type-hour">{{ trans('group.booking_types.rate_per_docent_hour') }}</Label>
                    <Input id="booking-type-hour" v-model="typeForm.rate_per_docent_hour" type="number" inputmode="decimal" min="0" step="0.01" />
                    <InputError :message="typeForm.errors.rate_per_docent_hour" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="dialogOpen = false">{{ trans('group.booking_types.cancel') }}</Button>
                    <Button type="submit" :disabled="typeForm.processing">{{ trans('group.booking_types.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="deleting !== null" @update:open="(open: boolean) => !open && (deleting = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('group.booking_types.delete_title', { type: deleting?.name ?? '' }) }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('group.booking_types.delete_body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ trans('group.booking_types.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/80" @click.prevent="confirmDelete">
                    {{ trans('group.booking_types.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
