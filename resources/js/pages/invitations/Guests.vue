<script setup lang="ts">
// A Host's guest list (ADR-0034 §3): add one current Member or a whole Audience, remove a
// Guest. The server resolves an Audience and checks that this Host may pick it. A removed
// Guest's Reply goes with them.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface Person {
    id: number;
    name: string;
}

interface GuestAudience {
    key: string;
    parameter: string | null;
    label: string;
}

const props = defineProps<{
    invitation: { id: number; name: string };
    guests: Person[];
    candidates: Person[];
    audiences: GuestAudience[];
}>();

const title = computed(() => trans('invitations.guests.title_for', { name: props.invitation.name }));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('invitations.title'), href: route('invitations.hosting') },
    { title: trans('invitations.guests.title'), href: '#' },
]);

const memberForm = useForm<{ member: number | '' }>({ member: '' });
const addMember = () => {
    memberForm.post(route('invitations.guests.store', { invitation: props.invitation.id }), {
        preserveScroll: true,
        onSuccess: () => memberForm.reset(),
    });
};

// The select holds an index into `audiences`, and the post names the picked Audience.
const pick = ref<number | ''>('');
const audienceForm = useForm<{ audience: string; parameter: string | null }>({ audience: '', parameter: null });
const addAudience = () => {
    if (pick.value === '') {
        return;
    }

    const { key, parameter } = props.audiences[pick.value];

    audienceForm
        .transform(() => ({ audience: key, parameter }))
        .post(route('invitations.guests.audience', { invitation: props.invitation.id }), {
            preserveScroll: true,
            onSuccess: () => (pick.value = ''),
        });
};

const remove = (guest: Person) => {
    if (window.confirm(trans('invitations.guests.confirm_remove'))) {
        router.delete(route('invitations.guests.destroy', { invitation: props.invitation.id, guest: guest.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>

            <form class="grid gap-2" @submit.prevent="addMember">
                <Label for="guest-member">{{ trans('invitations.guests.member') }}</Label>
                <div class="flex gap-2">
                    <NativeSelect id="guest-member" v-model="memberForm.member" required>
                        <option value="">—</option>
                        <option v-for="candidate in candidates" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option>
                    </NativeSelect>
                    <Button type="submit" variant="secondary" :disabled="memberForm.processing">{{ trans('invitations.guests.add') }}</Button>
                </div>
                <InputError :message="memberForm.errors.member" />
            </form>

            <form v-if="audiences.length" class="grid gap-2" @submit.prevent="addAudience">
                <Label for="guest-audience">{{ trans('invitations.guests.audience') }}</Label>
                <div class="flex gap-2">
                    <NativeSelect id="guest-audience" v-model="pick" required>
                        <option value="">—</option>
                        <option v-for="(audience, index) in audiences" :key="`${audience.key}:${audience.parameter}`" :value="index">
                            {{ audience.label }}
                        </option>
                    </NativeSelect>
                    <Button type="submit" variant="secondary" :disabled="audienceForm.processing">{{ trans('invitations.guests.add') }}</Button>
                </div>
                <InputError :message="audienceForm.errors.audience" />
            </form>

            <p v-if="!guests.length" class="text-muted-foreground text-sm">{{ trans('invitations.guests.empty') }}</p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ transChoice('invitations.guests.count', guests.length) }}</TableHead>
                        <TableHead class="text-right"
                            ><span class="sr-only">{{ trans('invitations.hosting.actions') }}</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="guest in guests" :key="guest.id">
                        <TableCell class="whitespace-normal">{{ guest.name }}</TableCell>
                        <TableCell class="text-right">
                            <Button type="button" variant="ghost" size="sm" @click="remove(guest)">{{ trans('invitations.guests.remove') }}</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
