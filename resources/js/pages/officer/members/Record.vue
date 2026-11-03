<script setup lang="ts">
// The Member record (#917, ADR-0038 §3): the Records office's page for one Member. One form, one
// PATCH, one Save, built on the DMV Settings cards. Category, status date and last sign-in are
// shown, never edited: Category moves through a Category history entry. Notes are a log: the page
// shows it and takes a new line, which the server dates and signs.
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const LINES = {
    identity: ['first_name', 'last_name', 'email'],
    contact: ['phone', 'alternate_phone', 'business_phone'],
    address: ['address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country'],
    emergency: ['emergency_contact', 'emergency_contact_number'],
} as const;
const AWARD_YEARS = ['entry_year', 'last_award_year', 'next_award_year'] as const;
const RENEWAL_YEARS = ['code_of_conduct_year', 'renewal_year'] as const;
const RENEWAL_TEXTS = ['renewal_placements', 'renewal_other', 'record_form_placements', 'record_form_other'] as const;
const BACKGROUND_TEXTS = ['volunteer_experience', 'past_experience', 'education'] as const;

interface RecordMember {
    id: number;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    alternate_phone: string | null;
    business_phone: string | null;
    standing: string;
    groups: { name: string; slug: string; roles: string[] }[];
    address: { street: string | null; city: string | null; province: string | null; postal_code: string | null; country: string | null };
    membership_number: string | null;
    membership_expiry: string | null;
    membership_lifetime: boolean | null;
    amount_paid: string | null;
    paid_through_year: number | null;
    fee_category: string | null;
    entry_year: number | null;
    service_awards: string[] | null;
    last_award_year: number | null;
    next_award_year: number | null;
    other_awards: string | null;
    emergency_contact: string | null;
    emergency_contact_number: string | null;
    newsletter_opt_in: boolean | null;
    status_date: string | null;
    code_of_conduct_year: number | null;
    renewal_year: number | null;
    renewal_status: string | null;
    renewal_placements: string | null;
    renewal_other: string | null;
    record_form_placements: string | null;
    record_form_other: string | null;
    mentor_name: string | null;
    volunteer_experience: string | null;
    past_experience: string | null;
    education: string | null;
    notes: string | null;
    last_sign_in_at: string | null;
}

const props = defineProps<{
    member: RecordMember;
    feeCategories: { code: string; name: string; amount: string }[];
    serviceAwards: { code: string; years: number }[];
    signInsCount: number;
    lastUpdated: { name: string; date: string } | null;
}>();

const page = usePage<SharedData>();

const fullName = computed(() => `${props.member.first_name} ${props.member.last_name}`);

// `computed` so the labels survive a full-page locale switch (the messages load async).
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: fullName.value, href: '#' },
]);

const form = useForm({
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    email: props.member.email ?? '',
    phone: props.member.phone ?? '',
    alternate_phone: props.member.alternate_phone ?? '',
    business_phone: props.member.business_phone ?? '',
    address_street: props.member.address.street ?? '',
    address_city: props.member.address.city ?? '',
    address_province: props.member.address.province ?? '',
    address_postal_code: props.member.address.postal_code ?? '',
    address_country: props.member.address.country ?? '',
    membership_number: props.member.membership_number ?? '',
    membership_expiry: props.member.membership_expiry ?? '',
    membership_lifetime: props.member.membership_lifetime ?? false,
    amount_paid: props.member.amount_paid ?? '',
    paid_through_year: props.member.paid_through_year ?? ('' as number | ''),
    fee_category: props.member.fee_category ?? '',
    entry_year: props.member.entry_year ?? ('' as number | ''),
    service_awards: props.member.service_awards ?? [],
    last_award_year: props.member.last_award_year ?? ('' as number | ''),
    next_award_year: props.member.next_award_year ?? ('' as number | ''),
    other_awards: props.member.other_awards ?? '',
    emergency_contact: props.member.emergency_contact ?? '',
    emergency_contact_number: props.member.emergency_contact_number ?? '',
    newsletter_opt_in: props.member.newsletter_opt_in ?? false,
    code_of_conduct_year: props.member.code_of_conduct_year ?? ('' as number | ''),
    renewal_year: props.member.renewal_year ?? ('' as number | ''),
    renewal_status: props.member.renewal_status ?? '',
    renewal_placements: props.member.renewal_placements ?? '',
    renewal_other: props.member.renewal_other ?? '',
    record_form_placements: props.member.record_form_placements ?? '',
    record_form_other: props.member.record_form_other ?? '',
    mentor_name: props.member.mentor_name ?? '',
    volunteer_experience: props.member.volunteer_experience ?? '',
    past_experience: props.member.past_experience ?? '',
    education: props.member.education ?? '',
    new_note: '',
});

// An instant, read in the org timezone like every other instant in the app.
const lastSignIn = computed(() =>
    props.member.last_sign_in_at
        ? new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: page.props.timezone }).format(
              new Date(props.member.last_sign_in_at),
          )
        : null,
);

const toggleLifetime = (checked: boolean) => {
    form.membership_lifetime = checked;
    if (checked) {
        form.membership_expiry = '';
    }
};

const toggleAward = (code: string, checked: boolean) => {
    form.service_awards = checked ? [...form.service_awards, code] : form.service_awards.filter((held) => held !== code);
};

// The yearly update, as the Legacy app's helper: a blank year stays blank.
const addPaidYear = () => {
    if (form.paid_through_year !== '') {
        form.paid_through_year = Number(form.paid_through_year) + 1;
    }
};

const save = () => {
    form.patch(route('members.record.update', { member: props.member.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset('new_note'),
    });
};
</script>

<template>
    <Head :title="fullName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <p class="text-muted-foreground text-sm">{{ trans('records.title') }}</p>
                <h1 class="text-rom-ink text-2xl font-semibold">{{ fullName }}</h1>
            </header>

            <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="save">
                <DmvSettingsSection id="standing" :title="trans('records.section.standing')">
                    <dl class="grid grid-cols-[max-content_1fr] items-center gap-x-4 gap-y-2 text-sm">
                        <dt class="text-muted-foreground">{{ trans('records.field.category') }}</dt>
                        <dd><StandingBadge :standing="member.standing" /></dd>
                        <template v-if="member.status_date">
                            <dt class="text-muted-foreground">{{ trans('records.field.status_date') }}</dt>
                            <dd>{{ formatDateOnly(member.status_date, page.props.locale) }}</dd>
                        </template>
                        <template v-if="member.last_sign_in_at">
                            <dt class="text-muted-foreground">{{ trans('records.field.last_sign_in_at') }}</dt>
                            <dd>{{ lastSignIn }}</dd>
                        </template>
                        <dt class="text-muted-foreground">{{ trans('records.field.sign_ins_count') }}</dt>
                        <dd>{{ signInsCount }}</dd>
                    </dl>
                </DmvSettingsSection>

                <DmvSettingsSection
                    v-for="(fields, section) in LINES"
                    :id="section"
                    :key="section"
                    :title="trans(`records.section.${section}`)"
                    layout="grid gap-4 sm:grid-cols-2"
                >
                    <div v-for="field in fields" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Input :id="field" v-model="form[field]" :type="field === 'email' ? 'email' : 'text'" />
                        <InputError :message="form.errors[field]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="rom" :title="trans('records.section.rom')" layout="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="membership_number">{{ trans('records.field.membership_number') }}</Label>
                        <Input id="membership_number" v-model="form.membership_number" />
                        <InputError :message="form.errors.membership_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="membership_expiry">{{ trans('records.field.membership_expiry') }}</Label>
                        <Input id="membership_expiry" v-model="form.membership_expiry" type="date" :disabled="form.membership_lifetime" />
                        <InputError :message="form.errors.membership_expiry" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="membership_lifetime" :checked="form.membership_lifetime" @update:checked="toggleLifetime" />
                        <Label for="membership_lifetime">{{ trans('records.field.membership_lifetime') }}</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="newsletter_opt_in"
                            :checked="form.newsletter_opt_in"
                            @update:checked="(checked: boolean) => (form.newsletter_opt_in = checked)"
                        />
                        <Label for="newsletter_opt_in">{{ trans('records.field.newsletter_opt_in') }}</Label>
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="dues" :title="trans('records.section.dues')" layout="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="amount_paid">{{ trans('records.field.amount_paid') }}</Label>
                        <Input id="amount_paid" v-model="form.amount_paid" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.amount_paid" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="paid_through_year">{{ trans('records.field.paid_through_year') }}</Label>
                        <div class="flex gap-2">
                            <Input id="paid_through_year" v-model="form.paid_through_year" type="number" />
                            <Button
                                type="button"
                                variant="outline"
                                :aria-label="trans('records.paid_through_add_year')"
                                :disabled="form.paid_through_year === ''"
                                @click="addPaidYear"
                            >
                                +1
                            </Button>
                        </div>
                        <InputError :message="form.errors.paid_through_year" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fee_category">{{ trans('records.field.fee_category') }}</Label>
                        <NativeSelect id="fee_category" v-model="form.fee_category">
                            <option value="">{{ trans('records.none') }}</option>
                            <option v-for="fee in feeCategories" :key="fee.code" :value="fee.code">{{ fee.name }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.fee_category" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="awards" :title="trans('records.section.awards')" layout="grid gap-4 sm:grid-cols-3">
                    <div v-for="field in AWARD_YEARS" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Input :id="field" v-model="form[field]" type="number" />
                        <InputError :message="form.errors[field]" />
                    </div>
                    <fieldset class="grid gap-2 sm:col-span-3">
                        <legend class="mb-2 text-sm font-medium">{{ trans('records.field.service_awards') }}</legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="award in serviceAwards" :key="award.code" class="flex items-center gap-2">
                                <Checkbox
                                    :id="`award-${award.years}`"
                                    :checked="form.service_awards.includes(award.code)"
                                    @update:checked="(checked: boolean) => toggleAward(award.code, checked)"
                                />
                                <Label :for="`award-${award.years}`">{{ trans('records.award_years', { years: String(award.years) }) }}</Label>
                            </div>
                        </div>
                        <InputError :message="form.errors.service_awards" />
                    </fieldset>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="other_awards">{{ trans('records.field.other_awards') }}</Label>
                        <Input id="other_awards" v-model="form.other_awards" />
                        <InputError :message="form.errors.other_awards" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="renewal" :title="trans('records.section.renewal')" layout="grid gap-4 sm:grid-cols-3">
                    <div v-for="field in RENEWAL_YEARS" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Input :id="field" v-model="form[field]" type="number" />
                        <InputError :message="form.errors[field]" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="renewal_status">{{ trans('records.field.renewal_status') }}</Label>
                        <Input id="renewal_status" v-model="form.renewal_status" />
                        <InputError :message="form.errors.renewal_status" />
                    </div>
                    <div v-for="field in RENEWAL_TEXTS" :key="field" class="grid gap-2 sm:col-span-3">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Textarea :id="field" v-model="form[field]" rows="2" />
                        <InputError :message="form.errors[field]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="background" :title="trans('records.section.background')">
                    <div class="grid gap-2">
                        <Label for="mentor_name">{{ trans('records.field.mentor_name') }}</Label>
                        <Input id="mentor_name" v-model="form.mentor_name" />
                        <InputError :message="form.errors.mentor_name" />
                    </div>
                    <div v-for="field in BACKGROUND_TEXTS" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Textarea :id="field" v-model="form[field]" rows="3" />
                        <InputError :message="form.errors[field]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="notes" :title="trans('records.section.notes')">
                    <div class="grid gap-2">
                        <Label for="new_note">{{ trans('records.field.new_note') }}</Label>
                        <Textarea id="new_note" v-model="form.new_note" rows="2" />
                        <InputError :message="form.errors.new_note" />
                    </div>
                    <p v-if="member.notes" class="text-sm whitespace-pre-wrap">{{ member.notes }}</p>
                    <p v-else class="text-muted-foreground text-sm">{{ trans('records.no_notes') }}</p>
                </DmvSettingsSection>

                <DmvSettingsSection id="groups" :title="trans('records.section.groups')">
                    <ul v-if="member.groups.length" class="flex flex-col gap-3 text-sm">
                        <li v-for="group in member.groups" :key="group.slug" class="flex flex-col gap-1.5">
                            <TextLink :href="route('groups.show', { group: group.slug })" class="font-medium">{{ group.name }}</TextLink>
                            <div v-if="group.roles.length" class="flex flex-wrap gap-1.5">
                                <Badge v-for="role in group.roles" :key="role" variant="secondary">{{ role }}</Badge>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground text-sm">{{ trans('member.no_groups') }}</p>
                </DmvSettingsSection>

                <div class="bg-background sticky bottom-0 z-10 flex flex-col gap-3 border-t py-3 sm:flex-row sm:items-center">
                    <p v-if="lastUpdated" class="text-muted-foreground text-sm">
                        {{ trans('records.last_updated', { name: lastUpdated.name, date: formatDateOnly(lastUpdated.date, page.props.locale) }) }}
                    </p>
                    <div class="flex items-center gap-4 sm:ml-auto">
                        <p v-if="form.recentlySuccessful" class="text-muted-foreground text-sm">{{ trans('records.saved') }}</p>
                        <Button type="submit" :disabled="form.processing">{{ trans('records.save') }}</Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
