<script setup lang="ts">
// A group tour's Earned (#797, ADR-0032 §7) — the line a Booker, Statistician, Chair or
// super-tier reads on the Shift card, marked worked out or corrected, and, for a viewer who may
// correct it (Statistician, Chair, super-tier), the correction form. Saving an empty field or
// pressing Clear sends null, which brings the worked-out figure back; 0 is a valid correction.
// The server re-checks every write (UpdateEarnedCorrectionRequest).
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData, type ShiftBooking } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { PhPencilSimple } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, useId } from 'vue';

const props = defineProps<{
    bookingId: number;
    officer: NonNullable<ShiftBooking['officer']>;
}>();

const page = usePage<SharedData>();
const money = (amount: string) => new Intl.NumberFormat(page.props.locale, { style: 'currency', currency: 'CAD' }).format(Number(amount));

const open = ref(false);
const fieldId = useId();
const form = useForm<{ earned_correction: string }>({ earned_correction: '' });
form.transform((data) => ({ earned_correction: data.earned_correction === '' ? null : data.earned_correction }));

const openForm = () => {
    form.earned_correction = props.officer.earned_correction ?? '';
    form.clearErrors();
    open.value = true;
};

const send = () => {
    form.patch(route('bookings.earned.update', { booking: props.bookingId }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};

const clear = () => {
    form.earned_correction = '';
    send();
};
</script>

<template>
    <p class="text-muted-foreground flex flex-wrap items-center gap-x-1">
        <span>{{ trans('group.bookings.earned.line', { amount: money(officer.earned) }) }}</span>
        <span>· {{ officer.earned_is_corrected ? trans('group.bookings.earned.corrected') : trans('group.bookings.earned.worked_out') }}</span>
        <Button
            v-if="officer.can_correct_earned"
            type="button"
            variant="ghost"
            size="icon"
            class="size-7"
            :aria-label="trans('group.bookings.earned.correct')"
            :title="trans('group.bookings.earned.correct')"
            @click="openForm"
        >
            <PhPencilSimple class="size-4" />
        </Button>
    </p>

    <Dialog v-if="officer.can_correct_earned" v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ trans('group.bookings.earned.title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="send">
                <div class="grid gap-2">
                    <Label :for="fieldId">{{ trans('group.bookings.earned.field') }}</Label>
                    <Input :id="fieldId" v-model="form.earned_correction" type="number" inputmode="decimal" min="0" step="0.01" />
                    <InputError :message="form.errors.earned_correction" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('group.bookings.earned.save') }}</Button>
                    <Button v-if="officer.earned_is_corrected" type="button" variant="outline" size="sm" :disabled="form.processing" @click="clear">
                        {{ trans('group.bookings.earned.clear') }}
                    </Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('group.bookings.earned.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
