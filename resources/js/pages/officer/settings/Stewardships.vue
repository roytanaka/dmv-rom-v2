<script setup lang="ts">
// DMV Settings › Stewardships (#846, ADR-0036 §7 and §8; restyled in #848): one card per live
// stewardship. Each card names the authority, its holder Groups and its last change, and saves on
// its own. Member administration has exactly one holder (a select, no empty option); org-wide
// sender has zero or more (tick rows). On any change the page reloads only the `preview` prop
// with the draft, and the server answers with the Members who gain and lose the authority; the
// preview panel shows them as chips, or says nothing has changed yet. Save stays off until the
// draft differs from the saved holders; Undo change puts them back. Group and Member names are
// content, shown as stored.
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

interface NamedRow {
    id: number;
    name: string;
}

interface StewardshipCard {
    holders: NamedRow[];
    updatedBy: string | null;
    updatedAt: string | null;
}

interface AuthorityDiff {
    gain: NamedRow[];
    lose: NamedRow[];
}

type LiveStewardship = 'member_admin' | 'org_mail';

const props = defineProps<{
    groupsHref: string;
    stewardshipsHref: string;
    stewardships: Record<LiveStewardship, StewardshipCard>;
    groups: (NamedRow & { depth: number })[];
    preview: Record<LiveStewardship, AuthorityDiff> | null;
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: props.groupsHref },
    { title: trans('dmv_settings.section.stewardships'), href: props.stewardshipsHref },
]);

// The saved holders, read live from the props so a save settles the draft as unchanged.
const savedMemberAdmin = computed(() => props.stewardships.member_admin.holders[0]?.id ?? null);
const savedOrgMail = computed(() => props.stewardships.org_mail.holders.map((holder) => holder.id));

const memberAdminForm = useForm<{ group_id: number | null }>({ group_id: savedMemberAdmin.value });
const orgMailForm = useForm<{ group_ids: number[] }>({ group_ids: savedOrgMail.value });

const sameIds = (a: number[], b: number[]): boolean => a.length === b.length && [...a].sort().every((id, index) => id === [...b].sort()[index]);
const dirty = computed<Record<LiveStewardship, boolean>>(() => ({
    member_admin: memberAdminForm.group_id !== savedMemberAdmin.value,
    org_mail: !sameIds(orgMailForm.group_ids, savedOrgMail.value),
}));

// Ask the server who gains and loses under the current draft of both cards.
const refreshPreview = () => {
    router.reload({
        only: ['preview'],
        data: { member_admin: memberAdminForm.group_id, org_mail: orgMailForm.group_ids },
        preserveUrl: true,
    });
};

// The select's v-model settles a tick after the native change, so watch the draft itself.
watch(() => memberAdminForm.group_id, refreshPreview);

const toggleOrgMail = (id: number, on: boolean) => {
    orgMailForm.group_ids = on ? [...orgMailForm.group_ids, id] : orgMailForm.group_ids.filter((groupId) => groupId !== id);
    refreshPreview();
};

const undo = (fn: LiveStewardship) => {
    if (fn === 'member_admin') {
        memberAdminForm.group_id = savedMemberAdmin.value;
        memberAdminForm.clearErrors();
    } else {
        orgMailForm.group_ids = savedOrgMail.value;
        orgMailForm.clearErrors();
        refreshPreview();
    }
};

const saveMemberAdmin = () => memberAdminForm.put(route('dmv-settings.stewardships.update', { function: 'member_admin' }), { preserveScroll: true });
const saveOrgMail = () => orgMailForm.put(route('dmv-settings.stewardships.update', { function: 'org_mail' }), { preserveScroll: true });

const heldBy = (card: StewardshipCard) =>
    card.holders.length === 0
        ? trans('dmv_settings.stewardship.held_by_none')
        : trans('dmv_settings.stewardship.held_by', { groups: card.holders.map((holder) => holder.name).join(', ') });

const lastChanged = (card: StewardshipCard) =>
    card.updatedBy && card.updatedAt
        ? trans('dmv_settings.last_changed', { name: card.updatedBy, date: formatDateOnly(card.updatedAt, page.props.locale) })
        : null;

const cards = computed(() =>
    (['member_admin', 'org_mail'] as const).map((fn) => ({
        fn,
        held: heldBy(props.stewardships[fn]),
        changed: lastChanged(props.stewardships[fn]),
        dirty: dirty.value[fn],
        // The diff shows only once the draft differs; an unchanged draft reads "No change yet".
        preview: dirty.value[fn] ? (props.preview?.[fn] ?? null) : null,
        processing: fn === 'member_admin' ? memberAdminForm.processing : orgMailForm.processing,
    })),
);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">{{ trans('dmv_settings.subtitle') }}</p>
            </div>

            <DmvSettingsTabs :groups-href="groupsHref" :stewardships-href="stewardshipsHref" />

            <div class="flex max-w-4xl flex-col gap-6">
                <Card v-for="card in cards" :key="card.fn">
                    <CardHeader class="gap-4 p-4 sm:p-6 md:flex-row md:items-start md:justify-between">
                        <div class="flex flex-col gap-1.5">
                            <CardTitle class="text-lg">{{ trans(`dmv_settings.stewardship.${card.fn}.title`) }}</CardTitle>
                            <CardDescription class="text-sm">{{ trans(`dmv_settings.stewardship.${card.fn}.description`) }}</CardDescription>
                        </div>
                        <div class="text-muted-foreground flex shrink-0 flex-col gap-1 text-sm md:max-w-xs md:text-right">
                            <p>{{ card.held }}</p>
                            <p v-if="card.changed">{{ card.changed }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 p-4 pt-0 sm:p-6 sm:pt-0">
                        <div v-if="card.fn === 'member_admin'" class="grid gap-2">
                            <Label for="stewardship-member-admin">{{ trans('dmv_settings.stewardship.group') }}</Label>
                            <NativeSelect id="stewardship-member-admin" v-model="memberAdminForm.group_id" required>
                                <option v-for="group in groups" :key="group.id" :value="group.id">
                                    {{ '— '.repeat(group.depth) }}{{ group.name }}
                                </option>
                            </NativeSelect>
                            <InputError :message="memberAdminForm.errors.group_id" />
                        </div>

                        <fieldset v-else class="flex flex-col gap-1.5">
                            <legend class="mb-1.5 text-sm font-medium">{{ trans('dmv_settings.stewardship.groups') }}</legend>
                            <div class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label v-for="group in groups" :key="group.id" class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                                    <Checkbox
                                        :checked="orgMailForm.group_ids.includes(group.id)"
                                        @update:checked="(on: boolean) => toggleOrgMail(group.id, on)"
                                    />
                                    {{ group.name }}
                                </label>
                            </div>
                            <InputError :message="orgMailForm.errors.group_ids" />
                        </fieldset>

                        <!-- The preview panel: who gains and loses under the draft, as chips. -->
                        <div class="bg-muted p-4 text-sm" aria-live="polite">
                            <p v-if="!card.preview" class="text-muted-foreground">{{ trans('dmv_settings.stewardship.no_change') }}</p>
                            <div v-else class="grid gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <p class="font-medium">
                                        {{ trans('dmv_settings.stewardship.gain', { count: String(card.preview.gain.length) }) }}
                                    </p>
                                    <div v-if="card.preview.gain.length > 0" class="flex flex-wrap gap-1.5">
                                        <Badge v-for="member in card.preview.gain" :key="member.id" variant="success">{{ member.name }}</Badge>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <p class="font-medium">
                                        {{ trans('dmv_settings.stewardship.lose', { count: String(card.preview.lose.length) }) }}
                                    </p>
                                    <div v-if="card.preview.lose.length > 0" class="flex flex-wrap gap-1.5">
                                        <Badge v-for="member in card.preview.lose" :key="member.id" variant="destructive">{{ member.name }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button v-if="card.dirty" type="button" variant="outline" :disabled="card.processing" @click="undo(card.fn)">
                                {{ trans('dmv_settings.stewardship.undo') }}
                            </Button>
                            <Button
                                type="button"
                                :disabled="!card.dirty || card.processing"
                                @click="card.fn === 'member_admin' ? saveMemberAdmin() : saveOrgMail()"
                            >
                                {{ trans('dmv_settings.save') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
