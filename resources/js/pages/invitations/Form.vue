<script setup lang="ts">
// Create or edit an Invitation (ADR-0034). An Option keeps its id through an edit, so the
// server renames its row instead of replacing it.
import InputError from '@/components/InputError.vue';
import TimeField from '@/components/TimeField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface EditableInvitation {
    id: number;
    name: string;
    date: string;
    start_time: string;
    end_time: string;
    description: string;
    guest_limit: number | null;
    options: { id: number | null; name: string }[];
}

const props = defineProps<{ invitation: EditableInvitation | null }>();

const title = computed(() => trans(props.invitation ? 'invitations.form.edit_title' : 'invitations.form.create_title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('invitations.title'), href: route('invitations.hosting') },
    { title: title.value, href: '#' },
]);

const form = useForm({
    name: props.invitation?.name ?? '',
    date: props.invitation?.date ?? '',
    start_time: props.invitation?.start_time ?? '',
    end_time: props.invitation?.end_time ?? '',
    description: props.invitation?.description ?? '',
    guest_limit: props.invitation?.guest_limit ?? '',
    options: props.invitation?.options ?? [],
});

const optionError = (index: number) => (form.errors as Record<string, string | undefined>)[`options.${index}.name`];

const submit = () => {
    if (props.invitation) {
        form.patch(route('invitations.update', { invitation: props.invitation.id }));
    } else {
        form.post(route('invitations.store'));
    }
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="invitation-name">{{ trans('invitations.form.name') }}</Label>
                    <Input id="invitation-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="invitation-date">{{ trans('invitations.form.date') }}</Label>
                    <Input id="invitation-date" v-model="form.date" type="date" required />
                    <InputError :message="form.errors.date" />
                </div>
                <div class="grid gap-2">
                    <Label for="invitation-start-time">{{ trans('invitations.form.start_time') }}</Label>
                    <TimeField id="invitation-start-time" v-model="form.start_time" required />
                    <InputError :message="form.errors.start_time" />
                </div>
                <div class="grid gap-2">
                    <Label for="invitation-end-time">{{ trans('invitations.form.end_time') }}</Label>
                    <TimeField id="invitation-end-time" v-model="form.end_time" required />
                    <InputError :message="form.errors.end_time" />
                </div>
                <div class="grid gap-2">
                    <Label for="invitation-description">{{ trans('invitations.form.description') }}</Label>
                    <Textarea id="invitation-description" v-model="form.description" required rows="6" />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label for="invitation-guest-limit">{{ trans('invitations.form.guest_limit') }}</Label>
                    <Input id="invitation-guest-limit" v-model="form.guest_limit" type="number" min="1" max="65535" />
                    <InputError :message="form.errors.guest_limit" />
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="text-sm font-medium">{{ trans('invitations.form.options') }}</legend>
                    <div v-for="(option, index) in form.options" :key="index" class="grid gap-2">
                        <div class="flex gap-2">
                            <Input v-model="option.name" required :aria-label="trans('invitations.form.option_name')" />
                            <Button type="button" variant="ghost" @click="form.options.splice(index, 1)">
                                {{ trans('invitations.form.remove_option') }}
                            </Button>
                        </div>
                        <InputError :message="optionError(index)" />
                    </div>
                    <div>
                        <Button type="button" variant="secondary" @click="form.options.push({ id: null, name: '' })">
                            {{ trans('invitations.form.add_option') }}
                        </Button>
                    </div>
                </fieldset>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">{{ trans('invitations.save') }}</Button>
                    <Button as-child variant="ghost">
                        <Link :href="route('invitations.hosting')">{{ trans('invitations.cancel') }}</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
