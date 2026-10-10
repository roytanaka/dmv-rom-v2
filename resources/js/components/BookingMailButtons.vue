<script setup lang="ts">
// Send a Booking's Request or Confirmation again (#799, ADR-0032 §9), for a Booker, the Chair or
// super-tier (the server's `can_send_mails` hint; the endpoint re-checks the BookingPolicy). The
// mail goes through the Delivery queue, so success means queued: a short line says so.
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{ bookingId: number }>();

type Mail = 'request' | 'confirmation';

const sending = ref(false);
const sent = ref<Mail | null>(null);

const send = (mail: Mail) => {
    sending.value = true;
    sent.value = null;
    router.post(
        route(mail === 'request' ? 'bookings.request' : 'bookings.confirmation', { booking: props.bookingId }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (sent.value = mail),
            onFinish: () => (sending.value = false),
        },
    );
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button type="button" size="sm" variant="outline" :disabled="sending" @click="send('request')">
            {{ trans('group.booking_mails.send_request') }}
        </Button>
        <Button type="button" size="sm" variant="outline" :disabled="sending" @click="send('confirmation')">
            {{ trans('group.booking_mails.send_confirmation') }}
        </Button>
        <p v-if="sent" class="text-muted-foreground text-sm" role="status">
            {{ trans(sent === 'request' ? 'group.booking_mails.request_sent' : 'group.booking_mails.confirmation_sent') }}
        </p>
    </div>
</template>
