<script setup lang="ts">
// /apply before the code (#949, ADR-0038 §8): the one place a visitor applies. While the Applicant
// code is open it asks for the code; otherwise it says applications are closed. Intake-meeting
// handouts give this address and the code.
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthHeroLayout from '@/layouts/auth/AuthHeroLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    open: boolean;
    phone: string;
}>();

const form = useForm({ code: '' });
// A POST keeps the code out of the URL and the logs; the server comes back to /apply.
const submit = () => form.post(route('apply.code'));
</script>

<template>
    <AuthHeroLayout locale-switch>
        <Head :title="trans('apply.title')" />

        <h1 id="apply-heading" class="mb-6 text-2xl font-semibold tracking-tight">{{ trans('apply.title') }}</h1>

        <form v-if="open" class="flex flex-col gap-6" aria-labelledby="apply-heading" @submit.prevent="submit">
            <p class="text-sm">{{ trans('apply.instruction') }}</p>
            <div class="grid gap-2">
                <Label for="applicant-code">{{ trans('apply.code_label') }}</Label>
                <div class="flex gap-2">
                    <Input id="applicant-code" v-model="form.code" required autofocus autocomplete="off" maxlength="64" />
                    <Button type="submit" :loading="form.processing">{{ trans('apply.continue') }}</Button>
                </div>
                <InputError :message="form.errors.code" />
            </div>
        </form>

        <p v-else class="text-sm">{{ trans('apply.closed', { phone }) }}</p>

        <p class="mt-8 text-sm">
            {{ trans('apply.already_volunteer') }}
            <TextLink :href="route('login')">{{ trans('apply.log_in') }}</TextLink>
        </p>
    </AuthHeroLayout>
</template>
