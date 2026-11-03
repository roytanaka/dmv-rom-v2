<script setup lang="ts">
// The public application form (#930, ADR-0038 §8): a visitor who typed the Applicant code on the
// sign-in page applies here, in English or French. The application's fields without the office's
// own, and the languages with one other at most. Lean form: labels, controls, errors.
import ApplicantFormFields from '@/components/applicants/ApplicantFormFields.vue';
import ApplicantLanguagesInput, { type LanguageRow } from '@/components/applicants/ApplicantLanguagesInput.vue';
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import { Button } from '@/components/ui/button';
import AuthHeroLayout from '@/layouts/auth/AuthHeroLayout.vue';
import { applicationFields } from '@/lib/applicants';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const page = usePage<SharedData>();
// The page's twin in the other language; a plain anchor so the i18n bridge reboots (#110).
const otherLocales = computed(() => page.props.localeSwitcher.options.filter((option) => option.url !== null));

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
    <AuthHeroLayout :title="trans('apply.title')" wide>
        <Head :title="trans('apply.title')" />

        <p v-if="otherLocales.length > 0" class="-mt-4 mb-6 text-sm">
            <a
                v-for="option in otherLocales"
                :key="option.code"
                :href="option.url ?? undefined"
                :lang="option.code"
                class="underline underline-offset-4"
            >
                {{ option.label }}
            </a>
        </p>

        <form class="flex flex-col gap-6" @submit.prevent="send">
            <ApplicantFormFields :fields="form" :errors="errors" />

            <DmvSettingsSection id="languages" :title="trans('applicants.languages.title')">
                <ApplicantLanguagesInput v-model:languages="form.languages" :errors="errors" :max-other="1" />
            </DmvSettingsSection>

            <Button type="submit" class="self-end" :loading="form.processing">{{ trans('apply.submit') }}</Button>
        </form>
    </AuthHeroLayout>
</template>
