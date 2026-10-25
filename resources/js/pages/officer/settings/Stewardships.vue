<script setup lang="ts">
// DMV Settings › Stewardships (#846, ADR-0036 §7 and §8): one card per live stewardship. Each card
// names the authority, its holder Groups and its last change, and saves on its own. Member
// administration has exactly one holder (a select, no empty option); org-wide sender has zero or
// more (tick rows). On any change the page reloads only the `preview` prop with the draft, and the
// server answers with the Members who gain and lose the authority. Group and Member names are
// content, shown as stored.
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import InputError from '@/components/InputError.vue';
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

const memberAdminForm = useForm<{ group_id: number | null }>({
    group_id: props.stewardships.member_admin.holders[0]?.id ?? null,
});
const orgMailForm = useForm<{ group_ids: number[] }>({
    group_ids: props.stewardships.org_mail.holders.map((holder) => holder.id),
});

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

const diffLines = (fn: LiveStewardship) => {
    const diff = props.preview?.[fn];
    if (!diff) return [];

    const line = (key: 'gain' | 'lose', rows: NamedRow[]) =>
        rows.length === 0
            ? []
            : [trans(`dmv_settings.stewardship.${key}`, { count: String(rows.length), names: rows.map((row) => row.name).join(', ') })];

    return [...line('gain', diff.gain), ...line('lose', diff.lose)];
};

const cards = computed(() =>
    (['member_admin', 'org_mail'] as const).map((fn) => ({
        fn,
        held: heldBy(props.stewardships[fn]),
        changed: lastChanged(props.stewardships[fn]),
        preview: diffLines(fn),
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

            <DmvSettingsTabs />

            <div class="flex max-w-3xl flex-col gap-6">
                <Card v-for="card in cards" :key="card.fn">
                    <CardHeader>
                        <CardTitle>{{ trans(`dmv_settings.stewardship.${card.fn}.title`) }}</CardTitle>
                        <CardDescription>{{ trans(`dmv_settings.stewardship.${card.fn}.description`) }}</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <p class="text-muted-foreground text-sm">
                            {{ card.held }}<template v-if="card.changed"> · {{ card.changed }}</template>
                        </p>

                        <div v-if="card.fn === 'member_admin'" class="grid gap-2">
                            <Label for="stewardship-member-admin">{{ trans('dmv_settings.stewardship.group') }}</Label>
                            <NativeSelect id="stewardship-member-admin" v-model="memberAdminForm.group_id" class="w-full sm:w-80" required>
                                <option v-for="group in groups" :key="group.id" :value="group.id">
                                    {{ '— '.repeat(group.depth) }}{{ group.name }}
                                </option>
                            </NativeSelect>
                            <InputError :message="memberAdminForm.errors.group_id" />
                        </div>

                        <fieldset v-else class="flex flex-col gap-1.5">
                            <legend class="mb-1.5 text-sm font-medium">{{ trans('dmv_settings.stewardship.groups') }}</legend>
                            <div class="grid gap-x-4 sm:grid-cols-2">
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

                        <div v-if="card.preview.length > 0" class="text-muted-foreground flex flex-col gap-1 text-sm">
                            <p v-for="line in card.preview" :key="line">{{ line }}</p>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="button"
                                size="sm"
                                :disabled="card.fn === 'member_admin' ? memberAdminForm.processing : orgMailForm.processing"
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
