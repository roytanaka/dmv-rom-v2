<script setup lang="ts">
// Group Meetings tab (#190, #193, PRD #186) — the Group's first own-data surface.
// Lists the Group's meetings (upcoming and past, newest first), each showing its
// date and time, location, the optional video link, and the agenda / minutes /
// report links.
//
// Officers (a Secretary / Chair / super-tier) get inline CRUD on top of the read
// surface, gated entirely by the server's `can` hints: a "New meeting" control, a
// per-meeting edit/delete pair, the published/hidden toggle, and a Draft badge on a
// hidden meeting. Every mutation is enforced by the MeetingPolicy regardless of
// what renders. Meeting title, description, and location are member-authored
// content, rendered as-authored; the link labels and everything else are translated
// chrome (ADR-0004).
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type Meeting, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { PhFileText, PhMapPin, PhPencilSimple, PhPlus, PhTrash, PhVideoCamera } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ meetings: Meeting[]; canCreate: boolean; groupSlug: string }>();

const page = usePage<SharedData>();
const formatDateTime = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));

// The conventional labelled-link set a meeting hangs off itself. Each is at most one
// URL; an empty field means the link is absent.
const LINK_KINDS = ['agenda', 'minutes', 'report'] as const;
type LinkKind = (typeof LINK_KINDS)[number];

// Render an ISO datetime as the `YYYY-MM-DDTHH:mm` a <input type="datetime-local">
// expects, in the viewer's local time.
const toDateTimeLocal = (iso: string) => {
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

// The open editor: 'create', or the id of the meeting being edited, or null when
// closed. Drives the single create/edit dialog.
const mode = ref<'create' | number | null>(null);

const form = useForm<{
    title: string;
    description: string;
    held_at: string;
    location: string;
    video_url: string;
    is_published: boolean;
    links: Record<LinkKind, string>;
}>({
    title: '',
    description: '',
    held_at: '',
    location: '',
    video_url: '',
    is_published: true,
    links: { agenda: '', minutes: '', report: '' },
});

// Shape the payload the server expects: optional text fields collapse empty strings
// to null (so a blank `video_url` is absent, not an invalid URL), and the links map
// becomes the labelled `{ kind, url }` array, dropping the blanks.
form.transform((data) => ({
    title: data.title,
    held_at: data.held_at,
    description: data.description || null,
    location: data.location || null,
    video_url: data.video_url || null,
    is_published: data.is_published,
    links: LINK_KINDS.filter((kind) => data.links[kind].trim() !== '').map((kind) => ({ kind, url: data.links[kind].trim() })),
}));

const dialogOpen = computed({
    get: () => mode.value !== null,
    set: (open: boolean) => {
        if (!open) close();
    },
});

const dialogTitle = computed(() => trans(mode.value === 'create' ? 'group.meetings.create_title' : 'group.meetings.edit_title'));

const openCreate = () => {
    form.reset();
    form.clearErrors();
    mode.value = 'create';
};

const openEdit = (meeting: Meeting) => {
    form.title = meeting.title;
    form.description = meeting.description ?? '';
    form.held_at = toDateTimeLocal(meeting.held_at);
    form.location = meeting.location ?? '';
    form.video_url = meeting.video_url ?? '';
    form.is_published = meeting.is_published;
    for (const kind of LINK_KINDS) {
        form.links[kind] = meeting.links.find((link) => link.kind === kind)?.url ?? '';
    }
    form.clearErrors();
    mode.value = meeting.id;
};

const close = () => {
    mode.value = null;
    form.reset();
};

const submit = () => {
    const onSuccess = () => close();
    if (mode.value === 'create') {
        form.post(route('meetings.store', { group: props.groupSlug }), { preserveScroll: true, onSuccess });
    } else if (mode.value !== null) {
        form.patch(route('meetings.update', { meeting: mode.value }), { preserveScroll: true, onSuccess });
    }
};

const destroy = (meeting: Meeting) => {
    if (window.confirm(trans('group.meetings.confirm_delete'))) {
        router.delete(route('meetings.destroy', { meeting: meeting.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="canCreate" class="flex justify-end">
            <Button type="button" size="sm" class="gap-1.5" @click="openCreate">
                <PhPlus class="size-4" />
                {{ trans('group.meetings.new') }}
            </Button>
        </div>

        <Card v-for="meeting in meetings" :key="meeting.id">
            <CardHeader>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        <CardTitle class="text-rom-ink flex items-center gap-2 text-lg">
                            {{ meeting.title }}
                            <Badge v-if="!meeting.is_published" variant="secondary">{{ trans('group.meetings.draft') }}</Badge>
                        </CardTitle>
                        <p class="text-muted-foreground text-sm">{{ formatDateTime(meeting.held_at) }}</p>
                    </div>
                    <div v-if="meeting.can.update || meeting.can.delete" class="flex shrink-0 gap-1">
                        <Button v-if="meeting.can.update" type="button" variant="ghost" size="sm" class="gap-1.5" @click="openEdit(meeting)">
                            <PhPencilSimple class="size-4" />
                            {{ trans('group.meetings.edit') }}
                        </Button>
                        <Button v-if="meeting.can.delete" type="button" variant="ghost" size="sm" class="gap-1.5" @click="destroy(meeting)">
                            <PhTrash class="size-4" />
                            {{ trans('group.meetings.delete') }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                <p v-if="meeting.description" class="text-rom-ink text-base whitespace-pre-line">{{ meeting.description }}</p>

                <p v-if="meeting.location" class="text-muted-foreground flex items-center gap-1.5 text-sm">
                    <PhMapPin class="size-4 shrink-0" />
                    {{ meeting.location }}
                </p>

                <p v-if="meeting.video_url" class="text-sm">
                    <TextLink :href="meeting.video_url" class="inline-flex items-center gap-1.5">
                        <PhVideoCamera class="size-4 shrink-0" />
                        {{ trans('group.meetings.video') }}
                    </TextLink>
                </p>

                <ul v-if="meeting.links.length" class="flex flex-wrap gap-x-4 gap-y-1.5 text-sm">
                    <li v-for="link in meeting.links" :key="`${link.kind}-${link.url}`">
                        <TextLink :href="link.url" class="inline-flex items-center gap-1.5">
                            <PhFileText class="size-4 shrink-0" />
                            {{ trans(`group.meetings.link.${link.kind}`) }}
                        </TextLink>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <p v-if="!meetings.length" class="text-muted-foreground py-12 text-center text-base">{{ trans('group.meetings.empty') }}</p>

        <!-- Officer create/edit dialog (#193) — one form, reused; opened by the
             "New meeting" control or a per-meeting edit. -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="meeting-title">{{ trans('group.meetings.field.title') }}</Label>
                        <Input id="meeting-title" v-model="form.title" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="meeting-held-at">{{ trans('group.meetings.field.held_at') }}</Label>
                        <Input id="meeting-held-at" v-model="form.held_at" type="datetime-local" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="meeting-description">{{ trans('group.meetings.field.description') }}</Label>
                        <Textarea id="meeting-description" v-model="form.description" :rows="4" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="meeting-location">{{ trans('group.meetings.field.location') }}</Label>
                        <Input id="meeting-location" v-model="form.location" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="meeting-video">{{ trans('group.meetings.field.video_url') }}</Label>
                        <Input id="meeting-video" v-model="form.video_url" type="url" />
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="text-muted-foreground mb-2 text-sm font-medium">{{ trans('group.meetings.field.links') }}</legend>
                        <div v-for="kind in LINK_KINDS" :key="kind" class="grid grid-cols-[6rem_1fr] items-center gap-2">
                            <Label :for="`meeting-link-${kind}`">{{ trans(`group.meetings.link.${kind}`) }}</Label>
                            <Input :id="`meeting-link-${kind}`" v-model="form.links[kind]" type="url" />
                        </div>
                    </fieldset>

                    <div class="flex items-center gap-3">
                        <Checkbox id="meeting-published" v-model:checked="form.is_published" />
                        <Label for="meeting-published">{{ trans('group.meetings.field.published') }}</Label>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('group.meetings.save') }}</Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="close">
                            {{ trans('group.meetings.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
