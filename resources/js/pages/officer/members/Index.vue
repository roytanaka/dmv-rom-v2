<script setup lang="ts">
// The Member lists (#919, ADR-0038 §5): Officer Tools → Members. Seven tabs, one route; the server
// sends the rows, the list's row actions and its Send email answer. The search is a query
// parameter, applied on submit. Edit opens the Member record; Reinstate posts (#920). Delete and
// Send email stay disabled until their tickets wire them. Photo (#922) opens a dialog that uploads or
// replaces the Member's photo and sets whether peers see it. Below `md` the row's facts ride under the name
// (docs/conventions.md § Tables on small screens). Names are content, shown as stored.
import type { NavNode } from '@/chrome/types';
import SectionTabs from '@/components/SectionTabs.vue';
import StandingBadge from '@/components/StandingBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import InputError from '@/components/InputError.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { PhEnvelopeSimple, PhListMagnifyingGlass, PhMagnifyingGlass } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

interface Row {
    id: number;
    first_name: string;
    last_name: string;
    standing: string;
    email: string | null;
    phone: string | null;
    last_update: string | null;
    photo: string | null;
    photo_public: boolean;
}

const props = defineProps<{
    list: string;
    tabs: { key: string; href: string }[];
    actions: string[];
    canSendEmail: boolean;
    search: string | null;
    members: {
        data: Row[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('records.members'));
const listLabel = computed(() => trans(`records.list.${props.list}`));
const listHref = computed(() => props.tabs.find((tab) => tab.key === props.list)?.href ?? props.tabs[0].href);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: listHref.value }]);
const tabItems = computed<NavNode[]>(() => props.tabs.map((tab) => ({ href: tab.href, labelKey: `records.list.${tab.key}` })));

// The actions a later ticket wires; each renders disabled until then.
const pendingActions = computed(() => props.actions.filter((action) => !['edit', 'photo', 'reinstate'].includes(action)));
const canReinstate = computed(() => props.actions.includes('reinstate'));

// Reinstate (#920): Reinstate then Active, dated today; the Member leaves this list.
const reinstate = (id: number) => {
    router.post(route('members.reinstate', { member: id }), {}, { preserveScroll: true });
};

// The Photo dialog (#922): one Member at a time, the file optional so the flag can change alone.
const photoMember = ref<Row | null>(null);
const photoOpen = ref(false);
const photoPreview = ref<string | null>(null);
const photoForm = useForm({ photo: null as File | null, photo_public: true });

const setPreview = (file: File | null) => {
    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }
    photoPreview.value = file ? URL.createObjectURL(file) : null;
};

const openPhoto = (member: Row) => {
    photoMember.value = member;
    photoForm.clearErrors();
    photoForm.photo = null;
    photoForm.photo_public = member.photo_public;
    setPreview(null);
    photoOpen.value = true;
};

const onPhotoChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    photoForm.photo = file;
    setPreview(file);
};

const savePhoto = () => {
    if (!photoMember.value) {
        return;
    }
    photoForm.post(route('members.photo.update', { member: photoMember.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            photoOpen.value = false;
            setPreview(null);
        },
    });
};

const term = ref(props.search ?? '');
watch(
    () => props.search,
    (value) => (term.value = value ?? ''),
);

const submitSearch = () => {
    const q = term.value.trim();
    router.get(listHref.value, q === '' ? {} : { q }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('officer.members.queries')">
                            <PhListMagnifyingGlass aria-hidden="true" />
                            {{ trans('saved_queries.title') }}
                        </Link>
                    </Button>
                    <Button v-if="canSendEmail" variant="outline" disabled>
                        <PhEnvelopeSimple aria-hidden="true" />
                        {{ trans('records.send_email') }}
                    </Button>
                </div>
            </header>

            <SectionTabs :items="tabItems" variant="body" />

            <form class="flex max-w-md items-end gap-2" role="search" @submit.prevent="submitSearch">
                <div class="flex flex-1 flex-col gap-1.5">
                    <Label for="member-list-search">{{ trans('records.search_last_name') }}</Label>
                    <Input id="member-list-search" v-model="term" type="search" autocomplete="off" />
                </div>
                <Button type="submit" variant="outline" :aria-label="trans('records.search')">
                    <PhMagnifyingGlass aria-hidden="true" />
                </Button>
            </form>

            <Table v-if="members.data.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('records.column.name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.category') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.email') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.phone') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('records.column.last_update') }}</TableHead>
                        <TableHead class="text-right">{{ trans('records.column.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="member in members.data" :key="member.id">
                        <TableCell class="whitespace-normal">
                            <span class="font-medium">{{ member.last_name }}, {{ member.first_name }}</span>
                            <span class="text-muted-foreground mt-1 flex flex-col gap-1 text-sm md:hidden">
                                <StandingBadge :standing="member.standing" class="w-fit" />
                                <a v-if="member.email" :href="`mailto:${member.email}`" class="break-all">{{ member.email }}</a>
                                <a v-if="member.phone" :href="`tel:${member.phone}`">{{ member.phone }}</a>
                            </span>
                        </TableCell>
                        <TableCell class="hidden md:table-cell"><StandingBadge :standing="member.standing" /></TableCell>
                        <TableCell class="hidden md:table-cell"
                            ><a v-if="member.email" :href="`mailto:${member.email}`">{{ member.email }}</a></TableCell
                        >
                        <TableCell class="hidden md:table-cell"
                            ><a v-if="member.phone" :href="`tel:${member.phone}`">{{ member.phone }}</a></TableCell
                        >
                        <TableCell class="hidden md:table-cell">
                            {{ member.last_update ? formatDateOnly(member.last_update, page.props.locale) : '' }}
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('officer.members.record', { member: member.id })">{{ trans('records.action.edit') }}</Link>
                                </Button>
                                <Button v-if="canReinstate" variant="outline" @click="reinstate(member.id)">
                                    {{ trans('records.action.reinstate') }}
                                </Button>
                                <Button v-if="actions.includes('photo')" variant="outline" @click="openPhoto(member)">
                                    {{ trans('records.action.photo') }}
                                </Button>
                                <Button v-for="action in pendingActions" :key="action" variant="outline" disabled>
                                    {{ trans(`records.action.${action}`) }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p v-else class="text-muted-foreground py-12 text-center text-base">
                {{ trans(search ? 'records.list_no_match' : 'records.list_empty') }}
            </p>

            <nav v-if="members.last_page > 1" class="flex flex-wrap items-center justify-between gap-4" :aria-label="listLabel">
                <Button v-if="members.prev_page_url" variant="outline" as-child>
                    <Link :href="members.prev_page_url" preserve-scroll>{{ trans('records.previous') }}</Link>
                </Button>
                <span v-else />
                <span class="text-muted-foreground text-sm tabular-nums">
                    {{ trans('records.page', { current: String(members.current_page), last: String(members.last_page) }) }}
                </span>
                <Button v-if="members.next_page_url" variant="outline" as-child>
                    <Link :href="members.next_page_url" preserve-scroll>{{ trans('records.next') }}</Link>
                </Button>
                <span v-else />
            </nav>

            <Dialog v-model:open="photoOpen">
                <DialogContent v-if="photoMember">
                    <form class="flex flex-col gap-4" @submit.prevent="savePhoto">
                        <DialogHeader>
                            <DialogTitle>{{
                                trans('records.photo.title', { name: `${photoMember.first_name} ${photoMember.last_name}` })
                            }}</DialogTitle>
                        </DialogHeader>
                        <Avatar size="lg">
                            <AvatarImage
                                v-if="photoPreview ?? photoMember.photo"
                                :src="photoPreview ?? photoMember.photo ?? ''"
                                :alt="`${photoMember.first_name} ${photoMember.last_name}`"
                            />
                            <AvatarFallback>{{
                                `${photoMember.first_name.charAt(0)}${photoMember.last_name.charAt(0)}`.toUpperCase()
                            }}</AvatarFallback>
                        </Avatar>
                        <div class="grid gap-2">
                            <Label for="records-photo">{{ trans('records.photo.file') }}</Label>
                            <input
                                id="records-photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="file:bg-secondary file:text-foreground hover:file:bg-secondary/80 block w-full text-sm text-neutral-700 file:mr-4 file:rounded-md file:border-0 file:px-4 file:py-2 file:text-sm file:font-medium"
                                @change="onPhotoChange"
                            />
                            <InputError :message="photoForm.errors.photo" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="records-photo-public"
                                :checked="photoForm.photo_public"
                                @update:checked="(checked: boolean) => (photoForm.photo_public = checked)"
                            />
                            <Label for="records-photo-public">{{ trans('records.photo.public') }}</Label>
                        </div>
                        <InputError :message="photoForm.errors.photo_public" />
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="photoOpen = false">{{ trans('records.photo.cancel') }}</Button>
                            <Button type="submit" :disabled="photoForm.processing">{{ trans('records.save') }}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
