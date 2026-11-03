<script setup lang="ts">
// The Applicant record (#928, ADR-0038 §8): one form, one PATCH, one Save, built on the DMV
// Settings cards as the Member record is. Status, placement, mentor and the reason (beside
// Incomplete) are selects; the status date is shown, never typed, since the server moves it with
// the status. Notes are a log: the page shows it and takes a new line, which the server dates and
// signs. A reader without edit rights (`canEdit` false) sees the same record, every field disabled.
import ApplicantFormFields from '@/components/applicants/ApplicantFormFields.vue';
import ApplicantLanguagesInput, { type LanguageRow } from '@/components/applicants/ApplicantLanguagesInput.vue';
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { applicationFields } from '@/lib/applicants';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const BACKGROUND_TEXTS = ['volunteer_experience', 'past_experience', 'education'] as const;

export interface RecordApplicant {
    id: number;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    alternate_phone: string | null;
    business_phone: string | null;
    address_street: string | null;
    address_city: string | null;
    address_province: string | null;
    address_postal_code: string | null;
    address_country: string | null;
    membership_number: string | null;
    membership_expiry: string | null;
    membership_lifetime: boolean | null;
    emergency_contact: string | null;
    emergency_contact_number: string | null;
    status: string;
    status_date: string | null;
    departure_reason: string | null;
    placement_group_id: number | null;
    mentor_id: number | null;
    entered_by_applicant: boolean;
    applied_on: string;
    entry_year: number | null;
    member_id: number | null;
    notes: string | null;
    volunteer_experience: string | null;
    past_experience: string | null;
    education: string | null;
    languages: LanguageRow[];
}

const props = defineProps<{
    applicant: RecordApplicant;
    canEdit: boolean;
    statuses: string[];
    departureReasons: string[];
    mentors: { id: number; name: string }[];
    placements: { id: number; name: string }[];
    enteredBy: string | null;
    lastUpdated: { name: string; date: string } | null;
    canOpenMember: boolean;
}>();

const page = usePage<SharedData>();

const fullName = computed(() => `${props.applicant.first_name} ${props.applicant.last_name}`);

// `computed` so the labels survive a full-page locale switch (the messages load async).
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('applicants.title'), href: route('officer.members.list.applicants') },
    { title: fullName.value, href: '#' },
]);

const form = useForm({
    ...applicationFields(props.applicant),
    status: props.applicant.status,
    departure_reason: props.applicant.departure_reason ?? '',
    placement_group_id: props.applicant.placement_group_id ?? ('' as number | ''),
    mentor_id: props.applicant.mentor_id ?? ('' as number | ''),
    applied_on: props.applicant.applied_on,
    entry_year: props.applicant.entry_year ?? ('' as number | ''),
    volunteer_experience: props.applicant.volunteer_experience ?? '',
    past_experience: props.applicant.past_experience ?? '',
    education: props.applicant.education ?? '',
    languages: props.applicant.languages.map((row) => ({ ...row })),
    new_note: '',
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);
const needsReason = computed(() => form.status === 'incomplete');

const enteredByLine = computed(() => {
    if (props.applicant.entered_by_applicant) {
        return trans('applicants.entered_by_applicant');
    }

    return props.enteredBy ? trans('applicants.entered_by', { name: props.enteredBy }) : null;
});

const save = () => {
    form.transform((data) => ({
        ...data,
        departure_reason: needsReason.value && data.departure_reason !== '' ? data.departure_reason : null,
        placement_group_id: data.placement_group_id === '' ? null : data.placement_group_id,
        mentor_id: data.mentor_id === '' ? null : data.mentor_id,
        entry_year: data.entry_year === '' ? null : data.entry_year,
        languages: data.languages.filter((row) => row.language_code.trim() !== ''),
    })).patch(route('applicants.update', { applicant: props.applicant.id }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('new_note');
            // Accept sets the entry year on the server (#931); keep the form in step with it.
            form.entry_year = props.applicant.entry_year ?? '';
        },
    });
};
</script>

<template>
    <Head :title="fullName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <p class="text-muted-foreground text-sm">{{ trans('applicants.title') }}</p>
                <h1 class="text-rom-ink text-2xl font-semibold">{{ fullName }}</h1>
                <p v-if="enteredByLine" class="text-muted-foreground text-sm">{{ enteredByLine }}</p>
                <Link
                    v-if="canOpenMember && applicant.member_id"
                    :href="route('officer.members.record', { member: applicant.member_id })"
                    class="text-sm underline"
                >
                    {{ trans('member.open_record') }}
                </Link>
            </header>

            <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="save">
                <DmvSettingsSection id="intake" :title="trans('applicants.section.intake')" layout="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="status">{{ trans('applicants.field.status') }}</Label>
                        <NativeSelect id="status" v-model="form.status" :disabled="!canEdit">
                            <option v-for="value in statuses" :key="value" :value="value">{{ trans(`applicants.status.${value}`) }}</option>
                        </NativeSelect>
                        <p v-if="applicant.status_date" class="text-muted-foreground text-sm">
                            {{ trans('applicants.field.status_date') }}: {{ formatDateOnly(applicant.status_date, page.props.locale) }}
                        </p>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <template v-if="needsReason">
                            <Label for="departure_reason">{{ trans('applicants.field.departure_reason') }}</Label>
                            <NativeSelect id="departure_reason" v-model="form.departure_reason" :disabled="!canEdit">
                                <option value="" disabled>{{ trans('records.history.choose') }}</option>
                                <option v-for="reason in departureReasons" :key="reason" :value="reason">
                                    {{ trans(`member.departure_reason.${reason}`) }}
                                </option>
                            </NativeSelect>
                            <InputError :message="form.errors.departure_reason" />
                        </template>
                    </div>
                    <div class="grid gap-2">
                        <Label for="placement_group_id">{{ trans('applicants.field.placement') }}</Label>
                        <NativeSelect id="placement_group_id" v-model="form.placement_group_id" :disabled="!canEdit">
                            <option value="">{{ trans('applicants.none') }}</option>
                            <option v-for="group in placements" :key="group.id" :value="group.id">{{ group.name }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.placement_group_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mentor_id">{{ trans('applicants.field.mentor') }}</Label>
                        <NativeSelect id="mentor_id" v-model="form.mentor_id" :disabled="!canEdit">
                            <option value="">{{ trans('applicants.unassigned') }}</option>
                            <option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.mentor_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="applied_on">{{ trans('applicants.field.applied_on') }}</Label>
                        <Input id="applied_on" v-model="form.applied_on" type="date" required :disabled="!canEdit" />
                        <InputError :message="form.errors.applied_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="entry_year">{{ trans('applicants.field.entry_year') }}</Label>
                        <Input id="entry_year" v-model="form.entry_year" type="number" :disabled="!canEdit" />
                        <InputError :message="form.errors.entry_year" />
                    </div>
                </DmvSettingsSection>

                <ApplicantFormFields :fields="form" :errors="errors" office :disabled="!canEdit" />

                <DmvSettingsSection id="languages" :title="trans('applicants.languages.title')">
                    <ApplicantLanguagesInput v-model:languages="form.languages" :errors="errors" :disabled="!canEdit" />
                </DmvSettingsSection>

                <DmvSettingsSection id="background" :title="trans('records.section.background')">
                    <div v-for="field in BACKGROUND_TEXTS" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Textarea :id="field" v-model="form[field]" rows="3" :disabled="!canEdit" />
                        <InputError :message="form.errors[field]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="notes" :title="trans('records.section.notes')">
                    <div v-if="canEdit" class="grid gap-2">
                        <Label for="new_note">{{ trans('records.field.new_note') }}</Label>
                        <Textarea id="new_note" v-model="form.new_note" rows="2" />
                        <InputError :message="form.errors.new_note" />
                    </div>
                    <p v-if="applicant.notes" class="text-sm whitespace-pre-wrap">{{ applicant.notes }}</p>
                    <p v-else class="text-muted-foreground text-sm">{{ trans('records.no_notes') }}</p>
                </DmvSettingsSection>

                <div class="bg-background sticky bottom-0 z-10 flex flex-col gap-3 border-t py-3 sm:flex-row sm:items-center">
                    <p v-if="lastUpdated" class="text-muted-foreground text-sm">
                        {{ trans('records.last_updated', { name: lastUpdated.name, date: formatDateOnly(lastUpdated.date, page.props.locale) }) }}
                    </p>
                    <div v-if="canEdit" class="flex items-center gap-4 sm:ml-auto">
                        <p v-if="form.recentlySuccessful" class="text-muted-foreground text-sm">{{ trans('applicants.saved') }}</p>
                        <Button type="submit" :disabled="form.processing">{{ trans('applicants.save') }}</Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
