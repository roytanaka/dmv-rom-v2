<script setup lang="ts">
// Step 2 of the Renewal (#940, ADR-0038 §9): the renewal form, pre-filled from the Member record,
// or in edit mode from what the Member saved for the year. Lean form: labels, controls, errors.
// A past ROM expiry warns but does not block the save.
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

type OtherPlacement = { group_id: number | null; position: string };

const props = defineProps<{
    year: number;
    editing: boolean;
    fields: {
        membership_number: string | null;
        membership_lifetime: boolean;
        membership_expiry: string | null;
        first_name: string;
        last_name: string;
        address_street: string | null;
        address_city: string | null;
        address_province: string | null;
        address_postal_code: string | null;
        phone: string | null;
        alternate_phone: string | null;
        business_phone: string | null;
        emergency_contact: string | null;
        emergency_contact_number: string | null;
        other: OtherPlacement[];
        other_texts: string[];
        category: string | null;
    };
    placements: { name: string; position: string; checked: boolean }[];
    groups: { id: number; name: string }[];
    positions: string[];
    categories: { code: string; name: string; extra: string | null; amount: string }[];
}>();

const title = computed(() => trans('renewal.title'));
const stepTitle = computed(() => trans('renewal.step.form'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: route('renew') },
    { title: stepTitle.value, href: route('renew.form') },
]);
const period = computed(() => trans('renewal.period', { year: String(props.year), next: String(props.year + 1) }));

const form = useForm({
    membership_number: props.fields.membership_number ?? '',
    membership_number_confirmed: props.editing,
    membership_lifetime: props.fields.membership_lifetime,
    membership_expiry: props.fields.membership_expiry ?? '',
    first_name: props.fields.first_name,
    last_name: props.fields.last_name,
    address_street: props.fields.address_street ?? '',
    address_city: props.fields.address_city ?? '',
    address_province: props.fields.address_province ?? '',
    address_postal_code: props.fields.address_postal_code ?? '',
    phone: props.fields.phone ?? '',
    alternate_phone: props.fields.alternate_phone ?? '',
    business_phone: props.fields.business_phone ?? '',
    placements: props.placements.flatMap((item, index) => (item.checked ? [index] : [])),
    other: props.fields.other.map((row) => ({ ...row })),
    other_texts: [...props.fields.other_texts],
    emergency_contact: props.fields.emergency_contact ?? '',
    emergency_contact_number: props.fields.emergency_contact_number ?? '',
    emergency_verified: props.editing,
    category: props.fields.category ?? '',
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);

const sections: { key: string; fields: (keyof typeof form & string)[] }[] = [
    { key: 'name', fields: ['first_name', 'last_name'] },
    { key: 'address', fields: ['address_street', 'address_city', 'address_province', 'address_postal_code'] },
    { key: 'phones', fields: ['phone', 'alternate_phone', 'business_phone'] },
];

const today = new Date().toISOString().slice(0, 10);
const expired = computed(() => !form.membership_lifetime && form.membership_expiry !== '' && form.membership_expiry < today);

const togglePlacement = (index: number, checked: boolean) => {
    form.placements = checked ? [...form.placements, index].sort((a, b) => a - b) : form.placements.filter((i) => i !== index);
};

const toggleLifetime = (checked: boolean) => {
    form.membership_lifetime = checked;
    if (checked) {
        form.membership_expiry = '';
    }
};

const setOtherGroup = (index: number, value: string) => {
    form.other[index].group_id = value === '' ? null : Number(value);
};

const submit = () => form.post(route('renew.form.store'));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ stepTitle }}</h1>
                <p class="text-muted-foreground text-base">{{ period }}</p>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <DmvSettingsSection id="rom" :title="trans('renewal.form.section.rom')" layout="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="membership_number">{{ trans('records.field.membership_number') }}</Label>
                        <Input id="membership_number" v-model="form.membership_number" required />
                        <InputError :message="errors.membership_number" />
                    </div>
                    <div class="flex flex-col justify-end gap-1.5">
                        <div class="flex items-center gap-2">
                            <Checkbox id="membership_number_confirmed" v-model:checked="form.membership_number_confirmed" />
                            <Label for="membership_number_confirmed">{{ trans('renewal.form.confirm_number') }}</Label>
                        </div>
                        <InputError :message="errors.membership_number_confirmed" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="membership_expiry">{{ trans('records.field.membership_expiry') }}</Label>
                        <Input id="membership_expiry" v-model="form.membership_expiry" type="date" :disabled="form.membership_lifetime" />
                        <InputError :message="errors.membership_expiry" />
                        <p v-if="expired" class="text-sm font-medium text-amber-700" role="status">{{ trans('renewal.form.expired') }}</p>
                    </div>
                    <div class="flex items-end gap-2 pb-2">
                        <Checkbox id="membership_lifetime" :checked="form.membership_lifetime" @update:checked="toggleLifetime" />
                        <Label for="membership_lifetime">{{ trans('records.field.membership_lifetime') }}</Label>
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection
                    v-for="section in sections"
                    :id="section.key"
                    :key="section.key"
                    :title="trans(`renewal.form.section.${section.key}`)"
                    layout="grid gap-4 sm:grid-cols-2"
                >
                    <div v-for="field in section.fields" :key="field" class="grid gap-2">
                        <Label :for="field">{{ trans(`records.field.${field}`) }}</Label>
                        <Input
                            :id="field"
                            v-model="form[field] as string"
                            :type="field.includes('phone') ? 'tel' : 'text'"
                            :required="field === 'first_name' || field === 'last_name'"
                        />
                        <InputError :message="errors[field]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="placements" :title="trans('renewal.form.section.placements')">
                    <p v-if="placements.length === 0" class="text-muted-foreground text-sm">{{ trans('renewal.form.no_placements') }}</p>
                    <div v-for="(item, index) in placements" :key="index" class="flex items-center gap-2">
                        <Checkbox
                            :id="`placement-${index}`"
                            :checked="form.placements.includes(index)"
                            @update:checked="(checked: boolean) => togglePlacement(index, checked)"
                        />
                        <Label :for="`placement-${index}`">{{ item.name }} ({{ item.position }})</Label>
                    </div>
                    <InputError :message="errors.placements" />
                </DmvSettingsSection>

                <DmvSettingsSection id="other" :title="trans('renewal.form.section.other')" layout="grid gap-4 sm:grid-cols-2">
                    <template v-for="(row, index) in form.other" :key="index">
                        <div class="grid gap-2">
                            <Label :for="`other-group-${index}`">{{ trans('renewal.form.other_group', { number: String(index + 1) }) }}</Label>
                            <NativeSelect
                                :id="`other-group-${index}`"
                                :model-value="row.group_id === null ? '' : String(row.group_id)"
                                @update:model-value="setOtherGroup(index, String($event ?? ''))"
                            >
                                <option value="">{{ trans('renewal.form.none') }}</option>
                                <option v-for="group in groups" :key="group.id" :value="String(group.id)">{{ group.name }}</option>
                            </NativeSelect>
                            <InputError :message="errors[`other.${index}.group_id`]" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`other-position-${index}`">{{ trans('renewal.form.other_position', { number: String(index + 1) }) }}</Label>
                            <NativeSelect :id="`other-position-${index}`" v-model="row.position">
                                <option v-for="position in positions" :key="position" :value="position">
                                    {{ trans(`renewal.form.positions.${position}`) }}
                                </option>
                            </NativeSelect>
                            <InputError :message="errors[`other.${index}.position`]" />
                        </div>
                    </template>
                    <div v-for="(_, index) in form.other_texts" :key="`text-${index}`" class="grid gap-2 sm:col-span-2">
                        <Label :for="`other-text-${index}`">{{ trans('renewal.form.other_text', { number: String(index + 1) }) }}</Label>
                        <Input :id="`other-text-${index}`" v-model="form.other_texts[index]" />
                        <InputError :message="errors[`other_texts.${index}`]" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="emergency" :title="trans('renewal.form.section.emergency')" layout="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="emergency_contact">{{ trans('records.field.emergency_contact') }}</Label>
                        <Input id="emergency_contact" v-model="form.emergency_contact" />
                        <InputError :message="errors.emergency_contact" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="emergency_contact_number">{{ trans('records.field.emergency_contact_number') }}</Label>
                        <Input id="emergency_contact_number" v-model="form.emergency_contact_number" type="tel" />
                        <InputError :message="errors.emergency_contact_number" />
                    </div>
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <div class="flex items-center gap-2">
                            <Checkbox id="emergency_verified" v-model:checked="form.emergency_verified" />
                            <Label for="emergency_verified">{{ trans('renewal.form.verify_emergency') }}</Label>
                        </div>
                        <InputError :message="errors.emergency_verified" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="category" :title="trans('renewal.form.section.category')">
                    <div class="grid gap-2">
                        <Label for="category">{{ trans('renewal.form.category') }}</Label>
                        <NativeSelect id="category" v-model="form.category" required>
                            <option value="" disabled>{{ trans('renewal.form.choose') }}</option>
                            <option v-for="fee in categories" :key="fee.code" :value="fee.code">
                                {{ fee.name }} (${{ fee.amount }}){{ fee.extra ? ` – ${fee.extra}` : '' }}
                            </option>
                            <option value="Resign">{{ trans('renewal.form.resign') }}</option>
                        </NativeSelect>
                        <InputError :message="errors.category" />
                    </div>
                </DmvSettingsSection>

                <Button type="submit" class="self-end" :loading="form.processing">{{ trans('renewal.form.save') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
