<script setup lang="ts">
// Add a group tour (#795, ADR-0032 §1, §10) — the Booker's form for a Booking. The staffing half
// (date, start, end, docents needed) becomes the Booking's one Shift on the month's group-tour
// Schedule, which the server creates on the month's first Booking. The client half is the
// Booking's own fields. On screen it is always a "group tour", never a "booking".
//
// The client field suggests the Group's past client names as the Booker types, read from the
// `bookings.clients` JSON endpoint into a native <datalist>. Every write is re-checked by
// StoreBookingRequest regardless of what renders.
import InputError from '@/components/InputError.vue';
import TimeField from '@/components/TimeField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { type BookingOptions } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, useId, watch } from 'vue';

const props = defineProps<{
    groupSlug: string;
    options: BookingOptions;
}>();

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
        clients.value = [];
    }
});

const close = () => {
    open.value = false;
};

const submit = () => {
    form.post(route('bookings.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

// --- Client suggestions (§10) ---------------------------------------------------------------

const clients = ref<string[]>([]);
const clientListId = useId();
let lookup: ReturnType<typeof setTimeout> | undefined;
let latest = 0;

// Ask for the Group's past client names a moment after the Booker stops typing. Only the newest
// answer lands, so a slow earlier reply never overwrites the list for what is typed now.
watch(
    () => form.client,
    (value) => {
        clearTimeout(lookup);
        const needle = value.trim();
        if (needle === '') {
            clients.value = [];
            return;
        }

        lookup = setTimeout(async () => {
            const request = ++latest;
            const response = await fetch(`${route('bookings.clients', { group: props.groupSlug })}?${new URLSearchParams({ q: needle })}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok || request !== latest) {
                return;
            }

            clients.value = ((await response.json()) as { clients: string[] }).clients;
        }, 200);
    },
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ trans('group.bookings.add_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="booking-client">{{ trans('group.bookings.field.client') }}</Label>
                    <Input id="booking-client" v-model="form.client" :list="clientListId" autocomplete="off" required />
                    <datalist :id="clientListId">
                        <option v-for="client in clients" :key="client" :value="client" />
                    </datalist>
                    <InputError :message="form.errors.client" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-tour">{{ trans('group.bookings.field.tour') }}</Label>
                    <NativeSelect id="booking-tour" v-model="form.tour_id" required>
                        <option :value="null" disabled>{{ trans('group.bookings.choose') }}</option>
                        <option v-for="tour in options.tours" :key="tour.id" :value="tour.id">{{ tour.name }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.tour_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="booking-type">{{ trans('group.bookings.field.type') }}</Label>
                    <NativeSelect id="booking-type" v-model="form.booking_type_id" required>
                        <option :value="null" disabled>{{ trans('group.bookings.choose') }}</option>
                        <option v-for="type in options.types" :key="type.id" :value="type.id">{{ type.name }}</option>
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
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('group.bookings.save') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="close">
                        {{ trans('group.bookings.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
