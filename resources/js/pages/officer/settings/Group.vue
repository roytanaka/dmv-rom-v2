<script setup lang="ts">
// DMV Settings › one Group (#843, ADR-0036 §5; restyled in #848): the structure form. One form,
// one PATCH, one Save; each field group is a DmvSettingsSection card with an id, and from `lg` a
// section index on the left links to them and marks the ones with a validation error. A save bar
// sticks to the bottom of the form with the last-changed line, the error count, Discard and Save
// changes. The Group's public address (localized, from the server) sits under the h1 as muted
// mono with a link to the page; its slug never changes. The root and Containers never reach this
// page (the server answers 404). An archived Group's form renders disabled under an info Alert
// (read-only, ADR-0036 §2) with no save bar and no Archive section, until it is unarchived
// (#845); an expired time-boxed Group stays editable so new dates can revive it. Every error
// lands under its control by field name; a `group` error (archived, root or Container) shows in
// the save bar. The ten flags and Time-boxed are DmvSettingsFlagRow switches; a capability that
// is on shows how many records sit behind it, and once switched off in the draft, how many
// become hidden (#844).
import { type ParentOption } from '@/components/CreateGroupDialog.vue';
import DmvSettingsFlagRow from '@/components/DmvSettingsFlagRow.vue';
import DmvSettingsSection from '@/components/DmvSettingsSection.vue';
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { PhArchive } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const CAPABILITIES = ['has_meetings', 'has_documents', 'has_scheduling', 'has_vetting', 'has_announcements', 'has_bookings'] as const;
const COLLECTIONS = [
    'collects_visitor_count',
    'collects_extra_interactions',
    'collects_visitor_provenance',
    'visitor_figures_await_booking',
] as const;
type Capability = (typeof CAPABILITIES)[number];
type Flag = Capability | (typeof COLLECTIONS)[number];

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
    // Saved state: only the capabilities that are on carry a count, with its label key (#844).
    counts: Partial<Record<Capability, { count: number; key: string }>>;
    groupHref: string;
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

type Field = keyof ReturnType<typeof form.data>;
const errors = computed(() => form.errors as Partial<Record<Field | 'group', string>>);

// The form's own `group` error (archived, root or Container): not a field of the form's data.
const groupError = computed(() => errors.value.group);
const errorCount = computed(() => Object.keys(form.errors).length);

function save(): void {
    form.transform((data) => ({
        ...data,
        start_date: data.time_boxed ? data.start_date || null : null,
        end_date: data.time_boxed ? data.end_date || null : null,
    })).patch(route('dmv-settings.groups.update', { group: props.group.slug }), { preserveScroll: true });
}

function discard(): void {
    form.reset();
    form.clearErrors();
}

// Archive and unarchive (#845, ADR-0036 §2): no fields; the route says which way. The stewardship
// guard and the root/Container refusal land on an `archive` error under the button.
const archiveForm = useForm({});
const confirmingArchive = ref(false);
const archiveError = computed(() => (archiveForm.errors as Record<string, string | undefined>).archive);

function archive(): void {
    archiveForm.patch(route('dmv-settings.groups.archive', { group: props.group.slug }), {
        preserveScroll: true,
        onFinish: () => (confirmingArchive.value = false),
    });
}

function unarchive(): void {
    archiveForm.patch(route('dmv-settings.groups.unarchive', { group: props.group.slug }), { preserveScroll: true });
}

// The section index: one entry per card, with the fields whose errors it marks. Archive has no
// field; its guard error marks it. An archived Group has no Archive section.
interface Section {
    id: string;
    key: string;
    fields: readonly Field[];
}
const SECTIONS: readonly Section[] = [
    { id: 'identity', key: 'identity', fields: ['name', 'parent_id', 'kind'] },
    { id: 'listing', key: 'listing', fields: ['listing_visibility', 'display_order', 'logo_key'] },
    { id: 'dates', key: 'dates', fields: ['time_boxed', 'start_date', 'end_date'] },
    { id: 'capabilities', key: 'capabilities', fields: CAPABILITIES },
    { id: 'visitor-figures', key: 'visitor_figures', fields: COLLECTIONS },
    { id: 'hours', key: 'hours', fields: ['hours_multiplier'] },
    { id: 'archive', key: 'archive', fields: [] },
];
const sections = computed(() => (props.group.archived ? SECTIONS.filter((section) => section.id !== 'archive') : SECTIONS));
const sectionTitle = (section: Section): string => trans(`dmv_settings.group.${section.key}`);
const sectionHasError = (section: Section): boolean =>
    section.id === 'archive' ? archiveError.value !== undefined : section.fields.some((field) => errors.value[field] !== undefined);

// Under a capability: the records behind it while it is on; once switched off in the draft,
// how many become hidden. Only a capability saved as on carries a count. Both label keys end
// in the relation name (`dmv_settings.count.shifts` ↔ `dmv_settings.hidden.shifts`).
const countLine = (flag: Capability): string | null => {
    const saved = props.counts[flag];
    if (!saved) return null;
    const relation = saved.key.split('.').pop();

    return transChoice(form[flag] ? saved.key : `dmv_settings.hidden.${relation}`, saved.count);
};
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
                <p class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                    <span class="text-muted-foreground font-mono">{{ groupHref }}</span>
                    <TextLink :href="groupHref">{{ trans('dmv_settings.group.open_page') }}</TextLink>
                </p>
            </div>

            <DmvSettingsTabs :groups-href="groupsHref" :stewardships-href="stewardshipsHref" />

            <div class="lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start lg:gap-8">
                <!-- The section index (lg+): in-page anchors, a dot on each section with an error. -->
                <nav
                    :aria-label="trans('dmv_settings.group.sections')"
                    class="hidden lg:sticky lg:top-[calc(var(--header-offset)+1.5rem)] lg:block lg:transition-[top] lg:duration-200 lg:ease-out lg:motion-reduce:transition-none"
                >
                    <ul class="flex flex-col gap-1">
                        <li v-for="section in sections" :key="section.id">
                            <a
                                :href="`#${section.id}`"
                                class="text-muted-foreground hover:text-rom-ink hover:bg-muted flex items-center gap-2 px-2 py-1.5 text-sm font-medium"
                            >
                                {{ sectionTitle(section) }}
                                <span
                                    v-if="sectionHasError(section)"
                                    role="img"
                                    :aria-label="trans('dmv_settings.group.has_problem')"
                                    class="bg-destructive size-2 shrink-0 rounded-full"
                                />
                            </a>
                        </li>
                    </ul>
                </nav>

                <form class="flex max-w-3xl min-w-0 flex-col gap-6" @submit.prevent="save">
                    <!-- Archived (#845): the banner names the state and carries the one action left. -->
                    <Alert v-if="group.archived" variant="info" role="status" class="flex-col sm:flex-row sm:items-start">
                        <PhArchive aria-hidden="true" />
                        <div class="flex flex-1 flex-col gap-1">
                            <AlertTitle class="text-rom-ink mb-0 font-semibold">{{ trans('dmv_settings.archived_banner.title') }}</AlertTitle>
                            <AlertDescription>{{ trans('dmv_settings.archived_banner.body') }}</AlertDescription>
                            <InputError :message="archiveError" />
                        </div>
                        <Button type="button" class="shrink-0" :disabled="archiveForm.processing" @click="unarchive">
                            {{ trans('dmv_settings.archive.unarchive') }}
                        </Button>
                    </Alert>

                    <fieldset class="flex flex-col gap-6" :disabled="group.archived">
                        <DmvSettingsSection id="identity" :title="sectionTitle(SECTIONS[0])">
                            <div class="grid gap-2">
                                <Label for="group-name">{{ trans('dmv_settings.field.name') }}</Label>
                                <Input id="group-name" v-model="form.name" required maxlength="255" />
                                <InputError :message="errors.name" />
                            </div>
                            <!-- Parent and Kind are a real pair: a subgrid keeps label, control and error
                                 in line across the two columns however a French label wraps. -->
                            <div class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                                <div class="row-span-3 grid grid-rows-subgrid gap-2">
                                    <Label for="group-parent">{{ trans('dmv_settings.field.parent') }}</Label>
                                    <NativeSelect id="group-parent" v-model="form.parent_id" required>
                                        <option v-for="option in parents" :key="option.id" :value="option.id">
                                            {{
                                                '— '.repeat(option.depth) +
                                                (option.inactive ? trans('dmv_settings.inactive_parent', { name: option.name }) : option.name)
                                            }}
                                        </option>
                                    </NativeSelect>
                                    <InputError :message="errors.parent_id" />
                                </div>
                                <div class="row-span-3 grid grid-rows-subgrid gap-2">
                                    <Label for="group-kind">{{ trans('dmv_settings.field.kind') }}</Label>
                                    <NativeSelect id="group-kind" v-model="form.kind" required>
                                        <option v-for="kind in kinds" :key="kind" :value="kind">{{ trans(`dmv_settings.kind.${kind}`) }}</option>
                                    </NativeSelect>
                                    <div class="flex flex-col gap-1">
                                        <p v-if="form.kind === 'container'" class="text-muted-foreground text-sm">
                                            {{ trans('dmv_settings.field.kind_container_note') }}
                                        </p>
                                        <InputError :message="errors.kind" />
                                    </div>
                                </div>
                            </div>
                        </DmvSettingsSection>

                        <DmvSettingsSection id="listing" :title="sectionTitle(SECTIONS[1])">
                            <div class="grid gap-2">
                                <Label for="group-visibility">{{ trans('dmv_settings.field.visibility') }}</Label>
                                <NativeSelect id="group-visibility" v-model="form.listing_visibility" required>
                                    <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                                        {{ trans(`dmv_settings.visibility.${visibility}`) }}
                                    </option>
                                </NativeSelect>
                                <InputError :message="errors.listing_visibility" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="group-display-order">{{ trans('dmv_settings.field.display_order') }}</Label>
                                <Input id="group-display-order" v-model.number="form.display_order" type="number" min="0" class="w-24" required />
                                <InputError :message="errors.display_order" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="group-logo">{{ trans('dmv_settings.field.logo') }}</Label>
                                <NativeSelect id="group-logo" v-model="form.logo_key">
                                    <option :value="null">{{ trans('dmv_settings.logo.none') }}</option>
                                    <option v-for="logo in logos" :key="logo" :value="logo">{{ trans(`dmv_settings.logo.${logo}`) }}</option>
                                </NativeSelect>
                                <InputError :message="errors.logo_key" />
                            </div>
                        </DmvSettingsSection>

                        <DmvSettingsSection id="dates" :title="sectionTitle(SECTIONS[2])">
                            <DmvSettingsFlagRow
                                id="group-time-boxed"
                                v-model="form.time_boxed"
                                :label="trans('dmv_settings.field.time_boxed')"
                                :error="errors.time_boxed"
                            />
                            <!-- Each date is a subgrid of label / input / error, so a French label that
                                 wraps never pushes its input out of line (docs/conventions.md). -->
                            <div v-if="form.time_boxed" class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                                <div class="row-span-3 grid grid-rows-subgrid gap-2">
                                    <Label for="group-start-date">{{ trans('dmv_settings.field.start_date') }}</Label>
                                    <Input id="group-start-date" v-model="form.start_date" type="date" required />
                                    <InputError :message="errors.start_date" />
                                </div>
                                <div class="row-span-3 grid grid-rows-subgrid gap-2">
                                    <Label for="group-end-date">{{ trans('dmv_settings.field.end_date') }}</Label>
                                    <Input id="group-end-date" v-model="form.end_date" type="date" required />
                                    <InputError :message="errors.end_date" />
                                </div>
                            </div>
                        </DmvSettingsSection>

                        <DmvSettingsSection id="capabilities" :title="sectionTitle(SECTIONS[3])" content-class="grid gap-3 md:grid-cols-2">
                            <DmvSettingsFlagRow
                                v-for="flag in CAPABILITIES"
                                :id="`group-${flag}`"
                                :key="flag"
                                v-model="form[flag]"
                                :label="trans(`dmv_settings.capability.${flag}`)"
                                :note="countLine(flag)"
                                :error="errors[flag]"
                            />
                        </DmvSettingsSection>

                        <DmvSettingsSection id="visitor-figures" :title="sectionTitle(SECTIONS[4])" content-class="grid gap-3 md:grid-cols-2">
                            <DmvSettingsFlagRow
                                v-for="flag in COLLECTIONS"
                                :id="`group-${flag}`"
                                :key="flag"
                                v-model="form[flag]"
                                :label="trans(`dmv_settings.collection.${flag}`)"
                                :error="errors[flag]"
                            />
                        </DmvSettingsSection>

                        <DmvSettingsSection id="hours" :title="sectionTitle(SECTIONS[5])">
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
                                <InputError :message="errors.hours_multiplier" />
                            </div>
                        </DmvSettingsSection>
                    </fieldset>

                    <!-- The Archive section (#845) sits outside the disabled fieldset; an archived
                         Group has the banner's Unarchive instead. -->
                    <DmvSettingsSection
                        v-if="!group.archived"
                        id="archive"
                        :title="sectionTitle(SECTIONS[6])"
                        content-class="flex flex-col items-start gap-3"
                    >
                        <p class="text-sm">{{ trans('dmv_settings.group.archive_note') }}</p>
                        <Button
                            type="button"
                            variant="outline"
                            class="text-destructive hover:text-destructive"
                            :disabled="archiveForm.processing"
                            @click="confirmingArchive = true"
                        >
                            {{ trans('dmv_settings.archive.button') }}
                        </Button>
                        <InputError :message="archiveError" />
                    </DmvSettingsSection>

                    <!-- The save bar: sticks to the bottom of the content while the form is in view. -->
                    <div
                        v-if="!group.archived"
                        class="bg-background sticky bottom-0 z-10 flex flex-col gap-3 border-t py-3 sm:flex-row sm:items-center"
                    >
                        <p v-if="lastChanged" class="text-muted-foreground text-sm">
                            {{
                                trans('dmv_settings.last_changed', {
                                    name: lastChanged.name,
                                    date: formatDateOnly(lastChanged.date, page.props.locale),
                                })
                            }}
                        </p>
                        <div class="flex flex-col gap-2 sm:ml-auto sm:flex-row sm:items-center sm:gap-4">
                            <div v-if="errorCount > 0" class="flex flex-col gap-1" role="status">
                                <p class="text-destructive text-sm font-medium">
                                    {{ transChoice('dmv_settings.group.problems', errorCount, { count: String(errorCount) }) }}
                                </p>
                                <InputError :message="groupError" />
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:flex">
                                <Button type="button" variant="outline" :disabled="form.processing" @click="discard">
                                    {{ trans('dmv_settings.group.discard') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing">{{ trans('dmv_settings.group.save_changes') }}</Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <AlertDialog v-model:open="confirmingArchive">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ trans('dmv_settings.archive.confirm_title', { name: group.name }) }}</AlertDialogTitle>
                        <AlertDialogDescription>{{ trans('dmv_settings.archive.confirm_body') }}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="archiveForm.processing">{{ trans('dmv_settings.archive.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/80"
                            :disabled="archiveForm.processing"
                            @click.prevent="archive"
                        >
                            {{ trans('dmv_settings.archive.confirm') }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
