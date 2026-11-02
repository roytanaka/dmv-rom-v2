<script setup lang="ts">
// One Invitation as a Guest reads it (ADR-0034 §10, §11): the occasion and the viewer's own
// Reply, which they may change until it is Closed. The controls come from the server's `can`
// hint; the server enforces the Reply.
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { toggledOption, type Answer, type Reply } from '@/invitations/reply';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    invitation: {
        id: number;
        name: string;
        date: string;
        start_time: string;
        end_time: string;
        description: string;
        host: string;
        closed: boolean;
        full: boolean;
        options: { id: number; name: string }[];
    };
    guest: { reply: Reply; options: number[] } | null;
    can: { reply: boolean; accept: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('invitations.title'), href: route('invitations') },
    { title: props.invitation.name, href: '#' },
]);

const locale = computed(() => usePage<SharedData>().props.locale);
const formattedDate = computed(() => formatDateOnly(props.invitation.date, locale.value, 'long'));

const chosenNames = computed(() =>
    props.invitation.options.filter((option) => props.guest?.options.includes(option.id)).map((option) => option.name),
);

const form = useForm<{ reply: Answer; options: number[] }>({
    reply: 'accepted',
    options: [...(props.guest?.options ?? [])],
});

const submit = (reply: Answer) => {
    form.reply = reply;
    form.put(route('invitations.reply', { invitation: props.invitation.id }), {
        preserveScroll: true,
        onSuccess: () => {
            form.options = [...(props.guest?.options ?? [])];
        },
    });
};
</script>

<template>
    <Head :title="invitation.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ invitation.name }}</h1>
                <p class="text-sm">{{ formattedDate }} · {{ invitation.start_time }}–{{ invitation.end_time }}</p>
                <p class="text-muted-foreground text-sm">{{ trans('invitations.show.hosted_by', { name: invitation.host }) }}</p>
            </div>

            <p class="text-sm whitespace-pre-line">{{ invitation.description }}</p>

            <p v-if="invitation.closed" class="text-muted-foreground text-sm">{{ trans('invitations.show.closed') }}</p>
            <p v-else-if="can.reply && !can.accept" class="text-muted-foreground text-sm">{{ trans('invitations.show.full') }}</p>

            <section v-if="guest" class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-medium">{{ trans('invitations.show.your_reply') }}</h2>
                    <Badge variant="secondary">{{ trans(`invitations.reply.${guest.reply}`) }}</Badge>
                </div>

                <p v-if="!can.reply && chosenNames.length" class="text-sm">{{ chosenNames.join(', ') }}</p>

                <form v-if="can.reply" class="flex flex-col gap-4" @submit.prevent="submit(can.accept ? 'accepted' : 'declined')">
                    <fieldset v-if="can.accept && invitation.options.length" class="flex flex-col gap-2">
                        <legend class="text-sm font-medium">{{ trans('invitations.form.options') }}</legend>
                        <div v-for="option in invitation.options" :key="option.id" class="flex items-center gap-2 pointer-coarse:min-h-11">
                            <Checkbox
                                :id="`option-${option.id}`"
                                :checked="form.options.includes(option.id)"
                                @update:checked="(on: boolean) => (form.options = toggledOption(form.options, option.id, on))"
                            />
                            <Label :for="`option-${option.id}`">{{ option.name }}</Label>
                        </div>
                        <InputError :message="form.errors.options" />
                    </fieldset>
                    <InputError :message="form.errors.reply" />

                    <div class="flex gap-2">
                        <Button v-if="can.accept" type="submit" :disabled="form.processing">{{ trans('invitations.reply.accept') }}</Button>
                        <Button type="button" variant="outline" :disabled="form.processing" @click="submit('declined')">
                            {{ trans('invitations.reply.decline') }}
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
