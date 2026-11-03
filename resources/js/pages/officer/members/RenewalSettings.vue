<script setup lang="ts">
// Renewal settings (#937, ADR-0038 §9): the one row member administration sets once a year to run
// the Renewal. The show flag and year, then the Active and Sustaining series (send date, two
// texts; Sustaining also an attachment), then the external addresses, one per line. The save
// goes as a POST with `_method=PUT`, so an attachment can ride along.
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{
    settings: {
        show_renewal: boolean;
        renewal_year: number | null;
        active_send_date: string | null;
        sustaining_send_date: string | null;
        active_message_1: string | null;
        active_message_2: string | null;
        sustaining_message_1: string | null;
        sustaining_message_2: string | null;
        external_addresses: string[];
        attachment: string | null;
    };
    counts: { renewed: number; paid: number } | null;
    lastUpdated: { name: string; date: string } | null;
}>();

const page = usePage<SharedData>();

const title = computed(() => trans('renewal_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: title.value, href: route('officer.members.renewal') },
]);

const form = useForm({
    _method: 'put',
    show_renewal: props.settings.show_renewal,
    renewal_year: props.settings.renewal_year === null ? '' : String(props.settings.renewal_year),
    active_send_date: props.settings.active_send_date ?? '',
    sustaining_send_date: props.settings.sustaining_send_date ?? '',
    active_message_1: props.settings.active_message_1 ?? '',
    active_message_2: props.settings.active_message_2 ?? '',
    sustaining_message_1: props.settings.sustaining_message_1 ?? '',
    sustaining_message_2: props.settings.sustaining_message_2 ?? '',
    external_addresses: [] as string[],
    sustaining_attachment: null as File | null,
});

const addresses = ref(props.settings.external_addresses.join('\n'));

// The first error on the list or any of its lines (the server names each line by index).
const addressesError = computed(() => {
    const errors = form.errors as Record<string, string>;
    const key = Object.keys(errors).find((name) => name === 'external_addresses' || name.startsWith('external_addresses.'));

    return key === undefined ? undefined : errors[key];
});

// Bumped after a save, so the file input remounts empty.
const fileKey = ref(0);

const onPick = (event: Event) => {
    form.sustaining_attachment = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const submit = () => {
    form.external_addresses = addresses.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

    form.post(route('renewal-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.sustaining_attachment = null;
            fileKey.value++;
        },
    });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>

            <ul v-if="counts" class="flex flex-col gap-1 text-base">
                <li>{{ trans('renewal_settings.counts.renewed', { count: String(counts.renewed), year: String(settings.renewal_year) }) }}</li>
                <li>{{ trans('renewal_settings.counts.paid', { count: String(counts.paid), year: String(settings.renewal_year) }) }}</li>
            </ul>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <DmvSettingsSection id="renewal" :title="trans('renewal_settings.section.renewal')">
                    <div class="flex items-center gap-2">
                        <Checkbox id="show_renewal" v-model:checked="form.show_renewal" />
                        <Label for="show_renewal">{{ trans('renewal_settings.field.show_renewal') }}</Label>
                    </div>
                    <InputError :message="form.errors.show_renewal" />
                    <div class="flex max-w-40 flex-col gap-1.5">
                        <Label for="renewal_year">{{ trans('renewal_settings.field.renewal_year') }}</Label>
                        <Input id="renewal_year" v-model="form.renewal_year" type="number" min="2000" max="2100" required />
                        <InputError :message="form.errors.renewal_year" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="active" :title="trans('renewal_settings.section.active')">
                    <div class="flex max-w-52 flex-col gap-1.5">
                        <Label for="active_send_date">{{ trans('renewal_settings.field.send_date') }}</Label>
                        <Input id="active_send_date" v-model="form.active_send_date" type="date" required />
                        <InputError :message="form.errors.active_send_date" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="active_message_1">{{ trans('renewal_settings.field.message_1') }}</Label>
                        <Textarea id="active_message_1" v-model="form.active_message_1" rows="5" required />
                        <InputError :message="form.errors.active_message_1" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="active_message_2">{{ trans('renewal_settings.field.message_2') }}</Label>
                        <Textarea id="active_message_2" v-model="form.active_message_2" rows="5" required />
                        <InputError :message="form.errors.active_message_2" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="sustaining" :title="trans('renewal_settings.section.sustaining')">
                    <div class="flex max-w-52 flex-col gap-1.5">
                        <Label for="sustaining_send_date">{{ trans('renewal_settings.field.send_date') }}</Label>
                        <Input id="sustaining_send_date" v-model="form.sustaining_send_date" type="date" required />
                        <InputError :message="form.errors.sustaining_send_date" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="sustaining_message_1">{{ trans('renewal_settings.field.message_1') }}</Label>
                        <Textarea id="sustaining_message_1" v-model="form.sustaining_message_1" rows="5" required />
                        <InputError :message="form.errors.sustaining_message_1" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="sustaining_message_2">{{ trans('renewal_settings.field.message_2') }}</Label>
                        <Textarea id="sustaining_message_2" v-model="form.sustaining_message_2" rows="5" required />
                        <InputError :message="form.errors.sustaining_message_2" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="sustaining_attachment">{{ trans('renewal_settings.field.attachment') }}</Label>
                        <p v-if="settings.attachment" class="text-sm">
                            <a
                                :href="route('officer.members.renewal.attachment')"
                                class="text-rom-slate decoration-rom-slate/40 hover:text-rom-slate-700 underline underline-offset-4"
                                >{{ settings.attachment }}</a
                            >
                        </p>
                        <Input id="sustaining_attachment" :key="fileKey" type="file" @change="onPick" />
                        <InputError :message="form.errors.sustaining_attachment" />
                    </div>
                </DmvSettingsSection>

                <DmvSettingsSection id="external" :title="trans('renewal_settings.section.external')">
                    <div class="flex flex-col gap-1.5">
                        <Label for="external_addresses">{{ trans('renewal_settings.field.external_addresses') }}</Label>
                        <Textarea id="external_addresses" v-model="addresses" rows="3" autocomplete="off" />
                        <InputError :message="addressesError" />
                    </div>
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
