<script setup lang="ts">
// Stop updates (#857, ADR-0029 §15): the page behind the signed link in every Follower email.
// No login. The page names the item, never the address. Opening it changes nothing (mail
// scanners open links); the one button posts back to the same signed URL to stop.
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    // confirm: the button. stopped: just stopped. already: the Follower or item is gone.
    state: 'confirm' | 'stopped' | 'already';
    itemLabel: string | null;
    stopHref: string;
}>();

const form = useForm({});

const message = computed(() => {
    if (props.state === 'confirm') {
        return trans('feedback.follow.stop.body', { item: props.itemLabel ?? '' });
    }

    return props.state === 'stopped' ? trans('feedback.follow.stop.stopped', { item: props.itemLabel ?? '' }) : trans('feedback.follow.stop.already');
});

function stop(): void {
    form.post(props.stopHref);
}
</script>

<template>
    <AuthLayout :title="trans('feedback.follow.stop.title')">
        <Head :title="trans('feedback.follow.stop.title')" />

        <div class="flex flex-col items-center gap-6 text-center">
            <p role="status">{{ message }}</p>
            <form v-if="state === 'confirm'" @submit.prevent="stop">
                <Button type="submit" :disabled="form.processing">{{ trans('feedback.follow.stop.button') }}</Button>
            </form>
        </div>
    </AuthLayout>
</template>
