<script setup lang="ts">
// The public application form (#930, ADR-0038 §8): a visitor who typed the Applicant code on
// /apply (#949) applies here, at the same URL, in English or French. The application's fields without the office's
// own, and the languages with one other at most. Lean form: labels, controls, errors.
import ApplicantFormFields from '@/components/applicants/ApplicantFormFields.vue';
import ApplicantLanguagesInput, { type LanguageRow } from '@/components/applicants/ApplicantLanguagesInput.vue';
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import { Button } from '@/components/ui/button';
import AuthHeroLayout from '@/layouts/auth/AuthHeroLayout.vue';
import { applicationFields } from '@/lib/applicants';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const form = useForm({
    ...applicationFields(),
    languages: [] as LanguageRow[],
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);

// The office-only fields ride along empty; the store does not take them.
const send = () => {
    form.transform((data) => ({
        ...data,
        languages: data.languages.filter((row) => row.language_code.trim() !== ''),
    })).post(route('apply.store'));
};
</script>

<template>
    <AuthHeroLayout :title="trans('apply.title')" wide locale-switch>
        <Head :title="trans('apply.title')" />

        <form class="flex flex-col gap-6" @submit.prevent="send">
            <ApplicantFormFields :fields="form" :errors="errors" />

            <DmvSettingsSection id="languages" :title="trans('applicants.languages.title')">
                <ApplicantLanguagesInput v-model:languages="form.languages" :errors="errors" :max-other="1" />
            </DmvSettingsSection>

            <Button type="submit" class="self-end" :loading="form.processing">{{ trans('apply.submit') }}</Button>
        </form>
    </AuthHeroLayout>
</template>
