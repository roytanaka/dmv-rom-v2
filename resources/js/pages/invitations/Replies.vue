<script setup lang="ts">
// The Host's Replies page (ADR-0034 §10, §11): totals, every Guest with their Reply and Options,
// and a Reply set for a Guest or for a Member not yet on the list. Print hands this page to the
// browser; the chrome and the controls carry `print:hidden`, so the heading, totals and list are
// what lands on paper. Email Accepted Guests opens the composer, which holds names only (#908).
// No address reaches this page.
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import { type AudienceOption } from '@/emailing/composer';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface ReplyRow {
    id: number;
    member: number;
    name: string;
    reply: 'no_reply' | 'accepted' | 'declined';
    options: number[];
}

const props = defineProps<{
    invitation: {
        id: number;
        name: string;
        host: string;
        date: string;
        start_time: string;
        end_time: string;
        options: { id: number; name: string }[];
    };
    totals: { accepted: number; declined: number; no_reply: number; options: { id: number; name: string; count: number }[] };
    guests: ReplyRow[];
    candidates: { id: number; name: string }[];
}>();

const title = computed(() => trans('invitations.replies.title_for', { name: props.invitation.name }));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('invitations.title'), href: route('invitations.hosting') },
    { title: trans('invitations.replies.title'), href: '#' },
]);

// `date` is a calendar day, so it formats in UTC to stay on the day the Host wrote.
const locale = computed(() => usePage<SharedData>().props.locale);
const formattedDate = computed(() =>
    new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(props.invitation.date)),
);

const optionNames = (guest: ReplyRow) =>
    props.invitation.options
        .filter((option) => guest.options.includes(option.id))
        .map((option) => option.name)
        .join(', ');

// One dialog serves a row's Guest and a Member not on the list: with a row, the Member is fixed.
const dialogOpen = ref(false);
const editing = ref<ReplyRow | null>(null);
const form = useForm<{ member: number | ''; reply: 'accepted' | 'declined'; options: number[] }>({
    member: '',
    reply: 'accepted',
    options: [],
});

const openDialog = (guest: ReplyRow | null) => {
    editing.value = guest;
    form.clearErrors();
    form.member = guest?.member ?? '';
    form.reply = guest?.reply === 'declined' ? 'declined' : 'accepted';
    form.options = [...(guest?.options ?? [])];
    dialogOpen.value = true;
};

const toggleOption = (id: number, on: boolean) => {
    form.options = on ? [...form.options, id] : form.options.filter((chosen) => chosen !== id);
};

const submit = () => {
    form.put(route('invitations.replies.update', { invitation: props.invitation.id }), {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    });
};

const print = () => window.print();

// No prefill: the prefilled mail invites, and these Guests have already said yes.
const composerOpen = ref(false);
const accepted = computed<AudienceOption>(() => ({
    key: 'invitation_accepted',
    parameter: null,
    label: trans('audience.invitation_accepted'),
    count: props.totals.accepted,
}));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                    <p class="text-sm">{{ formattedDate }} · {{ invitation.start_time }}–{{ invitation.end_time }}</p>
                </div>
                <div class="flex gap-2 print:hidden">
                    <Button type="button" variant="outline" size="sm" @click="print">{{ trans('invitations.replies.print') }}</Button>
                    <Button type="button" variant="outline" size="sm" :disabled="!totals.accepted" @click="composerOpen = true">
                        {{ trans('invitations.replies.email_accepted') }}
                    </Button>
                    <Button type="button" size="sm" @click="openDialog(null)">{{ trans('invitations.replies.add') }}</Button>
                </div>
            </div>

            <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div class="flex gap-1">
                    <dt class="text-muted-foreground">{{ trans('invitations.reply.accepted') }}</dt>
                    <dd class="font-medium">{{ totals.accepted }}</dd>
                </div>
                <div class="flex gap-1">
                    <dt class="text-muted-foreground">{{ trans('invitations.reply.declined') }}</dt>
                    <dd class="font-medium">{{ totals.declined }}</dd>
                </div>
                <div class="flex gap-1">
                    <dt class="text-muted-foreground">{{ trans('invitations.reply.no_reply') }}</dt>
                    <dd class="font-medium">{{ totals.no_reply }}</dd>
                </div>
                <div v-for="option in totals.options" :key="option.id" class="flex gap-1">
                    <dt class="text-muted-foreground">{{ option.name }}</dt>
                    <dd class="font-medium">{{ option.count }}</dd>
                </div>
            </dl>

            <p v-if="!guests.length" class="text-muted-foreground text-sm">{{ trans('invitations.guests.empty') }}</p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('invitations.replies.guest') }}</TableHead>
                        <TableHead>{{ trans('invitations.replies.reply') }}</TableHead>
                        <TableHead v-if="invitation.options.length">{{ trans('invitations.form.options') }}</TableHead>
                        <TableHead class="text-right print:hidden">
                            <span class="sr-only">{{ trans('invitations.hosting.actions') }}</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="guest in guests" :key="guest.id">
                        <TableCell class="whitespace-normal">{{ guest.name }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ trans(`invitations.reply.${guest.reply}`) }}</Badge>
                        </TableCell>
                        <TableCell v-if="invitation.options.length" class="whitespace-normal">{{ optionNames(guest) }}</TableCell>
                        <TableCell class="text-right print:hidden">
                            <Button type="button" variant="ghost" size="sm" @click="openDialog(guest)">
                                {{ trans('invitations.replies.set') }}
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing?.name ?? trans('invitations.replies.add') }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div v-if="!editing" class="grid gap-2">
                        <Label for="reply-member">{{ trans('invitations.replies.member') }}</Label>
                        <NativeSelect id="reply-member" v-model="form.member" required>
                            <option value="">—</option>
                            <option v-for="candidate in candidates" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.member" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reply-answer">{{ trans('invitations.replies.reply') }}</Label>
                        <NativeSelect id="reply-answer" v-model="form.reply" required>
                            <option value="accepted">{{ trans('invitations.reply.accepted') }}</option>
                            <option value="declined">{{ trans('invitations.reply.declined') }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.reply" />
                    </div>
                    <fieldset v-if="form.reply === 'accepted' && invitation.options.length" class="flex flex-col gap-2">
                        <legend class="text-sm font-medium">{{ trans('invitations.form.options') }}</legend>
                        <div v-for="option in invitation.options" :key="option.id" class="flex items-center gap-2">
                            <Checkbox
                                :id="`reply-option-${option.id}`"
                                :checked="form.options.includes(option.id)"
                                @update:checked="(on: boolean) => toggleOption(option.id, on)"
                            />
                            <Label :for="`reply-option-${option.id}`">{{ option.name }}</Label>
                        </div>
                        <InputError :message="form.errors.options" />
                    </fieldset>
                    <DialogFooter>
                        <Button type="button" variant="outline" :disabled="form.processing" @click="dialogOpen = false">
                            {{ trans('invitations.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">{{ trans('invitations.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ComposerSheet
            v-model:open="composerOpen"
            context="invitation"
            :context-subject="String(invitation.id)"
            :group-name="invitation.host"
            :roster="[]"
            :audience="accepted"
        />
    </AppLayout>
</template>
