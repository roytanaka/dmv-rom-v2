<script setup lang="ts">
// The fields of an application (#928, ADR-0038 §8), shared by Add applicant, the Applicant record
// and the public form (#930): names, email, phones, address, ROM membership and emergency contact.
// `office` adds what only the office keeps (business phone, country, the lifetime flag). The
// field names match the Member record's, so Accept copies them one for one. `disabled` renders the
// read-only view. Each block is a DmvSettingsSection, so the pages read like the Member record.
// Pass the page's `useForm` object as `:fields`; the inputs write into it.
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import type { ApplicationFields } from '@/lib/applicants';

type TextField = Exclude<keyof ApplicationFields, 'membership_lifetime'>;

const props = withDefaults(defineProps<{ errors: Partial<Record<string, string>>; office?: boolean; disabled?: boolean }>(), {
    office: false,
    disabled: false,
});

const fields = defineModel<ApplicationFields>('fields', { required: true });

const REQUIRED: TextField[] = ['first_name', 'last_name', 'email', 'phone'];

const sections = computed<{ key: string; fields: TextField[] }[]>(() => [
    { key: 'identity', fields: ['first_name', 'last_name', 'email'] },
    { key: 'contact', fields: props.office ? ['phone', 'alternate_phone', 'business_phone'] : ['phone', 'alternate_phone'] },
    {
        key: 'address',
        fields: props.office
            ? ['address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country']
            : ['address_street', 'address_city', 'address_province', 'address_postal_code'],
    },
    { key: 'emergency', fields: ['emergency_contact', 'emergency_contact_number'] },
]);

const typeOf = (field: TextField) => (field === 'email' ? 'email' : field.includes('phone') || field === 'emergency_contact_number' ? 'tel' : 'text');

const toggleLifetime = (checked: boolean) => {
    fields.value.membership_lifetime = checked;
    if (checked) {
        fields.value.membership_expiry = '';
    }
};
</script>

<template>
    <DmvSettingsSection
        v-for="section in sections"
        :id="section.key"
        :key="section.key"
        :title="trans(`records.section.${section.key}`)"
        layout="grid gap-4 sm:grid-cols-2"
    >
        <div v-for="field in section.fields" :key="field" class="grid gap-2">
            <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
            <Input
                :id="field"
                v-model="fields[field]"
                :type="typeOf(field)"
                :required="REQUIRED.includes(field)"
                :minlength="field === 'phone' ? 10 : undefined"
                :disabled="disabled"
            />
            <InputError :message="errors[field]" />
        </div>
    </DmvSettingsSection>

    <DmvSettingsSection id="rom" :title="trans('records.section.rom')" layout="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="membership_number">{{ trans('records.field.membership_number') }}</Label>
            <Input id="membership_number" v-model="fields.membership_number" :disabled="disabled" />
            <InputError :message="errors.membership_number" />
        </div>
        <div class="grid gap-2">
            <Label for="membership_expiry">{{ trans('records.field.membership_expiry') }}</Label>
            <Input id="membership_expiry" v-model="fields.membership_expiry" type="date" :disabled="disabled || fields.membership_lifetime" />
            <InputError :message="errors.membership_expiry" />
        </div>
        <div v-if="office" class="flex items-center gap-2">
            <Checkbox id="membership_lifetime" :checked="fields.membership_lifetime" :disabled="disabled" @update:checked="toggleLifetime" />
            <Label for="membership_lifetime">{{ trans('records.field.membership_lifetime') }}</Label>
        </div>
    </DmvSettingsSection>
</template>
