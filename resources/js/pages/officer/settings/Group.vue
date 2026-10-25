<script setup lang="ts">
// DMV Settings › one Group (#843, ADR-0036 §5): the structure form. One form, one PATCH, one Save;
// field groups are plain fieldsets. The slug sits under the h1 as muted mono and never changes. The
// root and Containers never reach this page (the server answers 404). An archived Group's form
// renders disabled (read-only, ADR-0036 §2) until it is unarchived (#845); an expired time-boxed
// Group stays editable so new dates can revive it. Every error lands under its control by field name;
// a `group` error (archived, root or Container) shows above Save.
import { type ParentOption } from '@/components/CreateGroupDialog.vue';
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const CAPABILITIES = ['has_meetings', 'has_documents', 'has_scheduling', 'has_vetting', 'has_announcements', 'has_bookings'] as const;
// The records each capability keeps reachable (#844), as `dmv_settings.count.*` keys.
const COUNT_KEYS: Record<(typeof CAPABILITIES)[number], string> = {
    has_meetings: 'meetings',
    has_documents: 'documents',
    has_scheduling: 'shifts',
    has_vetting: 'tours',
    has_announcements: 'posts',
    has_bookings: 'bookings',
};
const COLLECTIONS = [
    'collects_visitor_count',
    'collects_extra_interactions',
    'collects_visitor_provenance',
    'visitor_figures_await_booking',
] as const;
type Flag = (typeof CAPABILITIES)[number] | (typeof COLLECTIONS)[number];

interface StructureGroup extends Record<Flag, boolean> {
    id: number;
    slug: string;
    name: string;
    parent_id: number | null;
    kind: string;
    listing_visibility: string;
    display_order: number;
    logo_key: string | null;
    time_boxed: boolean;
    start_date: string | null;
    end_date: string | null;
    hours_multiplier: string;
    archived: boolean;
    endedOn: string | null;
}

const props = defineProps<{
    group: StructureGroup;
    parents: ParentOption[];
    kinds: string[];
    visibilities: string[];
    logos: string[];
    lastChanged: { name: string; date: string } | null;
    // Saved state: only the capabilities that are on carry a count.
    counts: Partial<Record<(typeof CAPABILITIES)[number], number>>;
    groupsHref: string;
    stewardshipsHref: string;
}>();

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: props.groupsHref },
    { title: props.group.name, href: '#' },
]);

const form = useForm({
    name: props.group.name,
    parent_id: props.group.parent_id,
    kind: props.group.kind,
    listing_visibility: props.group.listing_visibility,
    display_order: props.group.display_order,
    logo_key: props.group.logo_key,
    time_boxed: props.group.time_boxed,
    start_date: props.group.start_date ?? '',
    end_date: props.group.end_date ?? '',
    ...(Object.fromEntries([...CAPABILITIES, ...COLLECTIONS].map((flag) => [flag, props.group[flag]])) as Record<Flag, boolean>),
    hours_multiplier: Number(props.group.hours_multiplier),
});

// The form's own `group` error (archived, root or Container): not a field of the form's data.
const groupError = computed(() => (form.errors as Record<string, string | undefined>).group);

function save(): void {
    form.transform((data) => ({
        ...data,
        start_date: data.time_boxed ? data.start_date || null : null,
        end_date: data.time_boxed ? data.end_date || null : null,
    })).patch(route('dmv-settings.groups.update', { group: props.group.slug }), { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }}</h1>
                    <Badge v-if="group.archived" variant="secondary">{{ trans('dmv_settings.archived') }}</Badge>
                    <Badge v-else-if="group.endedOn" variant="warning">
                        {{ trans('group.ended', { date: formatDateOnly(group.endedOn, page.props.locale) }) }}
                    </Badge>
                </div>
                <p class="text-muted-foreground mt-1 font-mono text-sm">/groups/{{ group.slug }}</p>
            </div>

            <DmvSettingsTabs />

            <form class="flex max-w-2xl flex-col gap-6" @submit.prevent="save">
                <fieldset class="flex flex-col gap-6" :disabled="group.archived">
                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.identity') }}</legend>
                        <div class="grid gap-2">
                            <Label for="group-name">{{ trans('dmv_settings.field.name') }}</Label>
                            <Input id="group-name" v-model="form.name" required maxlength="255" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="group-parent">{{ trans('dmv_settings.field.parent') }}</Label>
                            <NativeSelect id="group-parent" v-model="form.parent_id" class="w-full sm:w-80" required>
                                <option v-for="option in parents" :key="option.id" :value="option.id">
                                    {{ '— '.repeat(option.depth) + option.name }}
                                </option>
                            </NativeSelect>
                            <InputError :message="form.errors.parent_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="group-kind">{{ trans('dmv_settings.field.kind') }}</Label>
                            <NativeSelect id="group-kind" v-model="form.kind" class="w-full sm:w-80" required>
                                <option v-for="kind in kinds" :key="kind" :value="kind">{{ trans(`dmv_settings.kind.${kind}`) }}</option>
                            </NativeSelect>
                            <p v-if="form.kind === 'container'" class="text-muted-foreground text-sm">
                                {{ trans('dmv_settings.field.kind_container_note') }}
                            </p>
                            <InputError :message="form.errors.kind" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.listing') }}</legend>
                        <div class="grid gap-2">
                            <Label for="group-visibility">{{ trans('dmv_settings.field.visibility') }}</Label>
                            <NativeSelect id="group-visibility" v-model="form.listing_visibility" class="w-full sm:w-80" required>
                                <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                                    {{ trans(`dmv_settings.visibility.${visibility}`) }}
                                </option>
                            </NativeSelect>
                            <InputError :message="form.errors.listing_visibility" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="group-display-order">{{ trans('dmv_settings.field.display_order') }}</Label>
                            <Input id="group-display-order" v-model.number="form.display_order" type="number" min="0" class="w-24" required />
                            <InputError :message="form.errors.display_order" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="group-logo">{{ trans('dmv_settings.field.logo') }}</Label>
                            <NativeSelect id="group-logo" v-model="form.logo_key" class="w-full sm:w-80">
                                <option :value="null">{{ trans('dmv_settings.logo.none') }}</option>
                                <option v-for="logo in logos" :key="logo" :value="logo">{{ trans(`dmv_settings.logo.${logo}`) }}</option>
                            </NativeSelect>
                            <InputError :message="form.errors.logo_key" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.dates') }}</legend>
                        <label class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                            <Checkbox :checked="form.time_boxed" @update:checked="(on: boolean) => (form.time_boxed = on)" />
                            {{ trans('dmv_settings.field.time_boxed') }}
                        </label>
                        <div v-if="form.time_boxed" class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="group-start-date">{{ trans('dmv_settings.field.start_date') }}</Label>
                                <Input id="group-start-date" v-model="form.start_date" type="date" required />
                                <InputError :message="form.errors.start_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="group-end-date">{{ trans('dmv_settings.field.end_date') }}</Label>
                                <Input id="group-end-date" v-model="form.end_date" type="date" required />
                                <InputError :message="form.errors.end_date" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.capabilities') }}</legend>
                        <div v-for="flag in CAPABILITIES" :key="flag" class="flex flex-col gap-1">
                            <label class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                                <Checkbox :checked="form[flag]" @update:checked="(on: boolean) => (form[flag] = on)" />
                                {{ trans(`dmv_settings.capability.${flag}`) }}
                                <span v-if="counts[flag] !== undefined" class="text-muted-foreground">
                                    {{ transChoice(`dmv_settings.count.${COUNT_KEYS[flag]}`, counts[flag]) }}
                                </span>
                            </label>
                            <InputError :message="form.errors[flag]" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.visitor_figures') }}</legend>
                        <div v-for="flag in COLLECTIONS" :key="flag" class="flex flex-col gap-1">
                            <label class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                                <Checkbox :checked="form[flag]" @update:checked="(on: boolean) => (form[flag] = on)" />
                                {{ trans(`dmv_settings.collection.${flag}`) }}
                            </label>
                            <InputError :message="form.errors[flag]" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-4 text-sm font-medium">{{ trans('dmv_settings.group.hours') }}</legend>
                        <div class="grid gap-2">
                            <Label for="group-hours-multiplier">{{ trans('dmv_settings.field.hours_multiplier') }}</Label>
                            <Input
                                id="group-hours-multiplier"
                                v-model.number="form.hours_multiplier"
                                type="number"
                                step="0.001"
                                min="0"
                                class="w-24"
                                required
                            />
                            <InputError :message="form.errors.hours_multiplier" />
                        </div>
                    </fieldset>

                    <div class="flex flex-col items-end gap-2">
                        <InputError :message="groupError" />
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('dmv_settings.save') }}</Button>
                    </div>
                </fieldset>
            </form>

            <p v-if="lastChanged" class="text-muted-foreground max-w-2xl text-sm">
                {{ trans('dmv_settings.last_changed', { name: lastChanged.name, date: formatDateOnly(lastChanged.date, page.props.locale) }) }}
            </p>
        </div>
    </AppLayout>
</template>
