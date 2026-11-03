<script setup lang="ts">
// Add applicant (#928, ADR-0038 §8): a holder member enters a paper or phone application. The
// application's fields and languages, plus a mentor select. An email a Member already has comes
// back as `duplicate_email`; the box to add the applicant anyway appears with it.
import ApplicantFormFields from '@/components/applicants/ApplicantFormFields.vue';
import ApplicantLanguagesInput, { type LanguageRow } from '@/components/applicants/ApplicantLanguagesInput.vue';
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { applicationFields } from '@/lib/applicants';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

defineProps<{ mentors: { id: number; name: string }[] }>();

const title = computed(() => trans('applicants.new_title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('applicants.title'), href: route('officer.members.list.applicants') },
    { title: title.value, href: '#' },
]);

const form = useForm({
    ...applicationFields(),
    languages: [] as LanguageRow[],
    mentor_id: '' as number | '',
    override_duplicate_email: false,
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);

const save = () => {
    form.transform((data) => ({
        ...data,
        mentor_id: data.mentor_id === '' ? null : data.mentor_id,
        languages: data.languages.filter((row) => row.language_code.trim() !== ''),
    })).post(route('applicants.store'));
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>

            <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="save">
                <DmvSettingsSection id="intake" :title="trans('applicants.section.intake')">
                    <div class="grid gap-2 sm:max-w-sm">
                        <Label for="mentor_id">{{ trans('applicants.field.mentor') }}</Label>
                        <NativeSelect id="mentor_id" v-model="form.mentor_id">
                            <option value="">{{ trans('applicants.unassigned') }}</option>
                            <option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.mentor_id" />
                    </div>
                </DmvSettingsSection>

                <ApplicantFormFields :fields="form" :errors="errors" office />

                <DmvSettingsSection id="languages" :title="trans('applicants.languages.title')">
                    <ApplicantLanguagesInput v-model:languages="form.languages" :errors="errors" />
                </DmvSettingsSection>

                <div class="bg-background sticky bottom-0 z-10 flex flex-col gap-3 border-t py-3 sm:flex-row sm:items-center">
                    <div v-if="errors.duplicate_email" class="flex flex-col gap-2">
                        <InputError :message="errors.duplicate_email" />
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="override_duplicate_email"
                                :checked="form.override_duplicate_email"
                                @update:checked="(checked: boolean) => (form.override_duplicate_email = checked)"
                            />
                            <Label for="override_duplicate_email">{{ trans('applicants.field.override_duplicate_email') }}</Label>
                        </div>
                    </div>
                    <Button type="submit" class="sm:ml-auto" :disabled="form.processing">{{ trans('applicants.save') }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
