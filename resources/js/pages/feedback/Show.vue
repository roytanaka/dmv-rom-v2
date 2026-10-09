<script setup lang="ts">
// One Feedback item's page (#677, ADR-0029 §8, §13): the message, its screenshots (#678,
// §9), the captured context, and the comments, oldest first. Any logged-in Member may comment. Outside production
// only. Type and status labels are chrome; the Tester's name, message, and comments are
// content, shown as sent.
//
// The comment form uses the Tester's remembered name (§5, `@/feedback/testerName`).
// Label, control, and error only — no helper text. A comment carries text, images (#778),
// or both; its images use the send dialog's image control, paste and drop included.
//
// A click on a screenshot or a comment image opens the image viewer (#776) on one list of
// every image on the page, in page order: the screenshots, then each comment's images.
//
// The Support-operator (`can.manage`, #679, §7) also sets the status, deletes the item, and
// deletes a comment. Each delete asks first. The server checks again on every request.
import FeedbackImagePicker from '@/components/FeedbackImagePicker.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatFeedbackDate, screenshotSize, STATUS_TONES, type FeedbackOption } from '@/feedback/display';
import { fileErrorMessages } from '@/feedback/screenshots';
import { rememberedName, rememberName } from '@/feedback/testerName';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ViewerEntry } from '@/viewer/entries';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface FeedbackItemDetail {
    id: number;
    typeLabelKey: string;
    status: string;
    statusLabelKey: string;
    testerName: string;
    message: string;
    createdAt: string;
    pageUrl: string | null;
    // The page URL as a link, set only when it is a path inside the app.
    pageHref: string | null;
    routeName: string | null;
    locale: string;
    userAgent: string | null;
    viewportWidth: number | null;
    viewportHeight: number | null;
    memberName: string;
    memberEmail: string;
    impersonatorName: string | null;
    appVersion: string | null;
}

// Each screenshot and comment image loads through its route, which checks the policy:
// `href` inline for the thumbnail and the viewer (#776), `downloadHref` as an attachment.
interface FeedbackImageRow {
    id: number;
    filename: string;
    sizeBytes: number;
    mimeType: string;
    href: string;
    downloadHref: string;
}

interface FeedbackCommentRow {
    id: number;
    testerName: string;
    // Null for a comment that carries only images.
    body: string | null;
    createdAt: string;
    images: FeedbackImageRow[];
    deleteHref: string;
}

const props = defineProps<{
    item: FeedbackItemDetail;
    screenshots: FeedbackImageRow[];
    comments: FeedbackCommentRow[];
    listHref: string;
    commentHref: string;
    can: { manage: boolean };
    statuses: FeedbackOption[];
    statusHref: string;
    deleteHref: string;
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('feedback.item.title', { id: String(props.item.id) }));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('feedback.title'), href: props.listHref },
    { title: title.value, href: page.url },
]);

const formatDate = (iso: string): string => formatFeedbackDate(iso, page.props.locale, page.props.timezone);

const viewport = computed(() =>
    props.item.viewportWidth === null || props.item.viewportHeight === null ? null : `${props.item.viewportWidth} × ${props.item.viewportHeight}`,
);

const toEntry = (image: FeedbackImageRow, kind: string): ViewerEntry => ({
    kind: 'file',
    key: `${kind}-${image.id}`,
    filename: image.filename,
    mimeType: image.mimeType,
    typeLabel: null,
    sizeBytes: null,
    src: image.href,
    downloadHref: image.downloadHref,
});

// Every image on the page, in page order: the screenshots, then each comment's images.
const viewerEntries = computed<ViewerEntry[]>(() => [
    ...props.screenshots.map((screenshot) => toEntry(screenshot, 'screenshot')),
    ...props.comments.flatMap((comment) => comment.images.map((image) => toEntry(image, 'comment-image'))),
]);
const viewerOpen = ref(false);
const viewerStart = ref(0);

function openViewer(key: string): void {
    viewerStart.value = Math.max(
        0,
        viewerEntries.value.findIndex((entry) => entry.key === key),
    );
    viewerOpen.value = true;
}

const form = useForm({
    tester_name: rememberedName(),
    body: '',
    images: [] as File[],
});
const picker = ref<InstanceType<typeof FeedbackImagePicker> | null>(null);

// The server's image errors: `images` for the count, `images.N` per file.
const serverImageErrors = computed(() => fileErrorMessages(form.errors as Record<string, string>, 'images'));

// The body and images are cleared by hand: after a success, useForm's defaults may be the
// sent values, so reset() could bring them back.
function submit(): void {
    form.post(props.commentHref, {
        preserveScroll: true,
        onSuccess: () => {
            rememberName(form.tester_name);
            form.body = '';
            form.images = [];
        },
    });
}

// The status picker saves on change. The badges read the item's status from the server.
// `preserveState` keeps a half-typed comment through the status change and a comment delete.
const status = computed({
    get: () => props.item.status,
    set: (value: string) => router.patch(props.statusHref, { status: value }, { preserveScroll: true, preserveState: true }),
});

const deletingItem = ref(false);
const deleteItemOpen = ref(false);

function deleteItem(): void {
    router.delete(props.deleteHref, {
        onStart: () => (deletingItem.value = true),
        onFinish: () => (deletingItem.value = false),
    });
}

// The comment waiting for the operator to confirm its delete; null when none is.
const pendingComment = ref<FeedbackCommentRow | null>(null);
const deletingComment = ref(false);

const deleteCommentOpen = computed({
    get: () => pendingComment.value !== null,
    set: (open: boolean) => {
        if (!open) pendingComment.value = null;
    },
});

function deleteComment(): void {
    if (pendingComment.value === null) return;

    router.delete(pendingComment.value.deleteHref, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (deletingComment.value = true),
        onSuccess: () => (pendingComment.value = null),
        onFinish: () => (deletingComment.value = false),
    });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-8 p-4 sm:p-6">
            <header class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ trans(item.typeLabelKey) }}</Badge>
                    <Badge :variant="STATUS_TONES[item.status]">{{ trans(item.statusLabelKey) }}</Badge>
                </div>
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">
                    {{ trans('feedback.item.sent_by', { name: item.testerName }) }} ·
                    <time :datetime="item.createdAt">{{ formatDate(item.createdAt) }}</time>
                </p>
            </header>

            <p class="text-rom-ink text-base break-words whitespace-pre-line">{{ item.message }}</p>

            <section v-if="screenshots.length" class="flex flex-col gap-3" aria-labelledby="feedback-item-screenshots">
                <h2 id="feedback-item-screenshots" class="text-rom-ink text-base font-semibold">{{ trans('feedback.screenshots.title') }}</h2>
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <li v-for="screenshot in screenshots" :key="screenshot.id">
                        <button
                            type="button"
                            class="group flex w-full cursor-pointer flex-col gap-1 text-left"
                            @click="openViewer(`screenshot-${screenshot.id}`)"
                        >
                            <img :src="screenshot.href" :alt="screenshot.filename" class="bg-muted aspect-video w-full border object-contain" />
                            <span
                                class="text-rom-slate decoration-rom-slate/40 group-hover:text-rom-slate-700 text-sm break-all underline underline-offset-4"
                                >{{ screenshot.filename }}</span
                            >
                            <span class="text-muted-foreground text-sm">{{ screenshotSize(screenshot.sizeBytes) }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <Card v-if="can.manage">
                <CardHeader>
                    <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('feedback.manage.title') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap items-end justify-between gap-4">
                    <div class="grid gap-2">
                        <Label for="feedback-status">{{ trans('feedback.manage.status') }}</Label>
                        <Select v-model="status">
                            <SelectTrigger id="feedback-status" class="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in statuses" :key="option.value" :value="option.value">{{
                                    trans(option.labelKey)
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="button" variant="destructive" size="sm" @click="deleteItemOpen = true">
                        <PhTrash class="size-4" />
                        {{ trans('feedback.manage.delete') }}
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('feedback.item.context') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-[max-content_1fr]">
                        <dt class="text-muted-foreground">{{ trans('feedback.item.page') }}</dt>
                        <dd class="text-rom-ink mb-2 break-all sm:mb-0">
                            <TextLink v-if="item.pageHref" :href="item.pageHref">{{ item.pageUrl }}</TextLink>
                            <template v-else>{{ item.pageUrl ?? trans('feedback.item.none') }}</template>
                        </dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.route') }}</dt>
                        <dd class="text-rom-ink mb-2 break-all sm:mb-0">{{ item.routeName ?? trans('feedback.item.none') }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.locale') }}</dt>
                        <dd class="text-rom-ink mb-2 sm:mb-0">{{ item.locale }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.browser') }}</dt>
                        <dd class="text-rom-ink mb-2 break-words sm:mb-0">{{ item.userAgent ?? trans('feedback.item.none') }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.viewport') }}</dt>
                        <dd class="text-rom-ink mb-2 sm:mb-0">{{ viewport ?? trans('feedback.item.none') }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.member') }}</dt>
                        <dd class="text-rom-ink mb-2 break-words sm:mb-0">{{ item.memberName }} · {{ item.memberEmail }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.impersonator') }}</dt>
                        <dd class="text-rom-ink mb-2 sm:mb-0">{{ item.impersonatorName ?? trans('feedback.item.none') }}</dd>
                        <dt class="text-muted-foreground">{{ trans('feedback.item.version') }}</dt>
                        <dd class="text-rom-ink">{{ item.appVersion ?? trans('feedback.item.none') }}</dd>
                    </dl>
                </CardContent>
            </Card>

            <section class="flex flex-col gap-4" aria-labelledby="feedback-comments">
                <h2 id="feedback-comments" class="text-rom-ink text-base font-semibold">{{ trans('feedback.comments.title') }}</h2>

                <ol v-if="comments.length" class="flex flex-col divide-y border-y">
                    <li v-for="comment in comments" :key="comment.id" class="flex flex-col gap-1 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm">
                                <span class="text-rom-ink font-semibold">{{ comment.testerName }}</span>
                                <span class="text-muted-foreground">
                                    · <time :datetime="comment.createdAt">{{ formatDate(comment.createdAt) }}</time></span
                                >
                            </p>
                            <Button
                                v-if="can.manage"
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="trans('feedback.comments.delete_label', { name: comment.testerName })"
                                @click="pendingComment = comment"
                            >
                                <PhTrash class="size-4" />
                                {{ trans('feedback.comments.delete') }}
                            </Button>
                        </div>
                        <p v-if="comment.body" class="text-rom-ink text-base break-words whitespace-pre-line">{{ comment.body }}</p>
                        <ul v-if="comment.images.length" class="mt-1 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <li v-for="image in comment.images" :key="image.id">
                                <button type="button" class="block w-full cursor-pointer" @click="openViewer(`comment-image-${image.id}`)">
                                    <img :src="image.href" :alt="image.filename" class="bg-muted aspect-video w-full border object-contain" />
                                </button>
                            </li>
                        </ul>
                    </li>
                </ol>
                <p v-else class="text-muted-foreground text-sm">{{ trans('feedback.comments.empty') }}</p>

                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submit"
                    @paste="picker?.onPaste($event)"
                    @dragover="picker?.onFormDragOver($event)"
                    @drop="picker?.onFormDrop($event)"
                >
                    <div class="grid gap-2">
                        <Label for="comment-name">{{ trans('feedback.comments.name') }}</Label>
                        <Input
                            id="comment-name"
                            v-model="form.tester_name"
                            required
                            maxlength="100"
                            autocomplete="name"
                            :aria-invalid="form.errors.tester_name ? true : undefined"
                        />
                        <InputError :message="form.errors.tester_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="comment-body">{{ trans('feedback.comments.body') }}</Label>
                        <Textarea
                            id="comment-body"
                            v-model="form.body"
                            maxlength="5000"
                            :rows="4"
                            :aria-invalid="form.errors.body ? true : undefined"
                        />
                        <InputError :message="form.errors.body" />
                    </div>
                    <FeedbackImagePicker
                        ref="picker"
                        v-model="form.images"
                        input-id="comment-images"
                        :label="trans('feedback.comments.images')"
                        :list-label="trans('feedback.comments.images_list')"
                        :server-errors="serverImageErrors"
                    />
                    <div>
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('feedback.comments.add') }}</Button>
                    </div>
                </form>
            </section>
        </div>

        <ImageViewer v-model:open="viewerOpen" :entries="viewerEntries" :start-index="viewerStart" />

        <template v-if="can.manage">
            <Dialog v-model:open="deleteItemOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ trans('feedback.manage.delete_title', { id: String(item.id) }) }}</DialogTitle>
                        <DialogDescription>{{ trans('feedback.manage.delete_body') }}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="ghost" size="sm" :disabled="deletingItem" @click="deleteItemOpen = false">
                            {{ trans('feedback.manage.cancel') }}
                        </Button>
                        <Button type="button" variant="destructive" size="sm" :disabled="deletingItem" @click="deleteItem">
                            {{ trans('feedback.manage.delete') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="deleteCommentOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ trans('feedback.comments.delete_title') }}</DialogTitle>
                        <DialogDescription>{{
                            trans('feedback.comments.delete_body', { name: pendingComment?.testerName ?? '' })
                        }}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="ghost" size="sm" :disabled="deletingComment" @click="deleteCommentOpen = false">
                            {{ trans('feedback.manage.cancel') }}
                        </Button>
                        <Button type="button" variant="destructive" size="sm" :disabled="deletingComment" @click="deleteComment">
                            {{ trans('feedback.comments.delete_confirm') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>
    </AppLayout>
</template>
