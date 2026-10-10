<script setup lang="ts">
// Add a group tour (#795, ADR-0032 §1, §10) — the Booker's form for a Booking. The staffing half
// (date, start, end, docents needed) becomes the Booking's one Shift on the month's group-tour
// Schedule, which the server creates on the month's first Booking. The client half is the
// Booking's own fields. On screen it is always a "group tour", never a "booking".
//
// The client field suggests the Group's past client names (§10) through a native <datalist>, which
// filters them as the Booker types. The names are the page's optional `bookingClients` prop, loaded
// by a partial reload when the dialog opens. Every write is re-checked by StoreBookingRequest
// regardless of what renders.
//
// Given a `booking`, the same form changes it (#796): filled from the Booking's values and sent as
// a PATCH to `bookings.update`, re-checked by UpdateBookingRequest. A retired Tour or type the
// Booking still names stays on offer, so an old Booking stays editable.
import InputError from '@/components/InputError.vue';
import TimeField from '@/components/TimeField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { type BookingOptions, type ShiftBooking } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, useId, watch } from 'vue';

const props = defineProps<{
    groupSlug: string;
    options: BookingOptions;
    clients: string[] | null;
    booking?: ShiftBooking | null;
}>();

const editing = computed(() => props.booking?.edit ?? null);

// The pickers, plus the Booking's own Tour and type when they are retired and so not offered.
const tourOptions = computed(() => {
    const edit = editing.value;
    if (!edit || !props.booking || props.options.tours.some((tour) => tour.id === edit.tour_id)) return props.options.tours;
    return [...props.options.tours, { id: edit.tour_id, name: props.booking.tour }];
});
const typeOptions = computed(() => {
    const edit = editing.value;
    if (!edit || props.options.types.some((type) => type.id === edit.booking_type_id)) return props.options.types;
    return [...props.options.types, { id: edit.booking_type_id, name: edit.booking_type }];
});

const open = defineModel<boolean>('open', { default: false });

const form = useForm<{
    date: string;
    starts_time: string;
    ends_time: string;
    docents_needed: number;
    tour_id: number | null;
    booking_type_id: number | null;
    client: string;
    visitors: number | '';
    leader: string;
    order_number: string;
    order_date: string;
    comments: string;
}>({
    date: '',
    starts_time: '',
    ends_time: '',
    docents_needed: 1,
    tour_id: null,
    booking_type_id: null,
    client: '',
    visitors: '',
    leader: '',
    order_number: '',
    order_date: '',
    comments: '',
});

// Blank optional fields go as null, so the server stores them absent rather than empty.
form.transform((data) => ({
    ...data,
    visitors: data.visitors === '' ? null : data.visitors,
    leader: data.leader || null,
    order_number: data.order_number || null,
    order_date: data.order_date || null,
    comments: data.comments || null,
}));

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        const edit = editing.value;
        if (edit) {
            form.date = edit.date;
            form.starts_time = edit.starts_time;
            form.ends_time = edit.ends_time;
            form.docents_needed = edit.docents_needed;
            form.tour_id = edit.tour_id;
            form.booking_type_id = edit.booking_type_id;
            form.client = edit.client;
            form.visitors = edit.visitors;
            form.leader = edit.leader ?? '';
            form.order_number = edit.order_number ?? '';
            form.order_date = edit.order_date ?? '';
            form.comments = edit.comments ?? '';
        }
        router.reload({ only: ['bookingClients'] });
    }
});

const close = () => {
    open.value = false;
};

const submit = () => {
    if (props.booking && editing.value) {
        form.patch(route('bookings.update', { booking: props.booking.id }), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
        return;
    }

    form.post(route('bookings.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

// --- Client suggestions (§10) ---------------------------------------------------------------

const clientListId = useId();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ editing ? trans('group.bookings.edit_title') : trans('group.bookings.add_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="booking-client">{{ trans('group.bookings.field.client') }}</Label>
                    <Input id="booking-client" v-model="form.client" :list="clientListId" autocomplete="off" required />
                    <datalist :id="clientListId">
                        <option v-for="client in clients ?? []" :key="client" :value="client" />
                    </datalist>
                    <InputError :message="form.errors.client" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-tour">{{ trans('group.bookings.field.tour') }}</Label>
                    <NativeSelect id="booking-tour" v-model="form.tour_id" required>
                        <option :value="null" disabled>{{ trans('group.bookings.choose') }}</option>
                        <option v-for="tour in tourOptions" :key="tour.id" :value="tour.id">{{ tour.name }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.tour_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-type">{{ trans('group.bookings.field.type') }}</Label>
                    <NativeSelect id="booking-type" v-model="form.booking_type_id" required>
                        <option :value="null" disabled>{{ trans('group.bookings.choose') }}</option>
                        <option v-for="type in typeOptions" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.booking_type_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-date">{{ trans('group.bookings.field.date') }}</Label>
                    <Input id="booking-date" v-model="form.date" type="date" required />
                    <InputError :message="form.errors.date" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="booking-starts">{{ trans('group.bookings.field.starts_time') }}</Label>
                        <TimeField id="booking-starts" v-model="form.starts_time" required />
                        <InputError :message="form.errors.starts_time" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="booking-ends">{{ trans('group.bookings.field.ends_time') }}</Label>
                        <TimeField id="booking-ends" v-model="form.ends_time" required />
                        <InputError :message="form.errors.ends_time" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="booking-docents">{{ trans('group.bookings.field.docents_needed') }}</Label>
                        <Input id="booking-docents" v-model.number="form.docents_needed" type="number" min="1" required />
                        <InputError :message="form.errors.docents_needed" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="booking-visitors">{{ trans('group.bookings.field.visitors') }}</Label>
                        <Input id="booking-visitors" v-model.number="form.visitors" type="number" min="0" required />
                        <InputError :message="form.errors.visitors" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="booking-leader">{{ trans('group.bookings.field.leader') }}</Label>
                    <Input id="booking-leader" v-model="form.leader" />
                    <InputError :message="form.errors.leader" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="booking-order-number">{{ trans('group.bookings.field.order_number') }}</Label>
                        <Input id="booking-order-number" v-model="form.order_number" />
                        <InputError :message="form.errors.order_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="booking-order-date">{{ trans('group.bookings.field.order_date') }}</Label>
                        <Input id="booking-order-date" v-model="form.order_date" type="date" />
                        <InputError :message="form.errors.order_date" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="booking-comments">{{ trans('group.bookings.field.comments') }}</Label>
                    <Textarea id="booking-comments" v-model="form.comments" :rows="3" />
                    <InputError :message="form.errors.comments" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{
                        editing ? trans('group.bookings.update') : trans('group.bookings.save')
                    }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="close">
                        {{ trans('group.bookings.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
