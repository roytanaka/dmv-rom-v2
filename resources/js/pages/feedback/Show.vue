<script setup lang="ts">
// One Feedback item's page (#677, ADR-0029 §8, §13): the message, its screenshots (#678,
// §9), the captured context, and the comments, oldest first. Any logged-in Member may comment. Outside production
// only. Type and status labels are chrome; the Tester's name, message, and comments are
// content, shown as sent.
//
// The comment form uses the Tester's remembered name (§5, `@/feedback/testerName`).
// Label, control, and error only — no helper text.
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatFeedbackDate, STATUS_TONES } from '@/feedback/display';
import { sizeLabel } from '@/feedback/screenshots';
import { rememberedName, rememberName } from '@/feedback/testerName';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

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

// Each screenshot loads through its download route, which checks the policy.
interface FeedbackScreenshotRow {
    id: number;
    filename: string;
    sizeBytes: number;
    href: string;
}

interface FeedbackCommentRow {
    id: number;
    testerName: string;
    body: string;
    createdAt: string;
}

const props = defineProps<{
    item: FeedbackItemDetail;
    screenshots: FeedbackScreenshotRow[];
    comments: FeedbackCommentRow[];
    listHref: string;
    commentHref: string;
}>();

const page = usePage<SharedData>();

// `computed` so the labels survive a full-page locale switch (the messages load async).
const title = computed(() => trans('feedback.item.title', { id: String(props.item.id) }));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('feedback.title'), href: props.listHref },
    { title: title.value, href: page.url },
]);

const formatDate = (iso: string): string => formatFeedbackDate(iso, page.props.locale, page.props.timezone);

function sizeText(bytes: number): string {
    const { key, size } = sizeLabel(bytes);

    return trans(key, { size });
}

const viewport = computed(() =>
    props.item.viewportWidth === null || props.item.viewportHeight === null ? null : `${props.item.viewportWidth} × ${props.item.viewportHeight}`,
);

const form = useForm({
    tester_name: rememberedName(),
    body: '',
});

function submit(): void {
    form.post(props.commentHref, {
        preserveScroll: true,
        onSuccess: () => {
            rememberName(form.tester_name);
            form.reset('body');
        },
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

            <section v-if="screenshots.length" class="flex flex-col gap-3" aria-labelledby="feedback-screenshots">
                <h2 id="feedback-screenshots" class="text-rom-ink text-base font-semibold">{{ trans('feedback.screenshots.title') }}</h2>
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <li v-for="screenshot in screenshots" :key="screenshot.id">
                        <!-- A plain link, not TextLink: TextLink makes an Inertia visit, and this is a file download. -->
                        <a :href="screenshot.href" class="group flex flex-col gap-1">
                            <img :src="screenshot.href" :alt="screenshot.filename" class="bg-muted aspect-video w-full border object-contain" />
                            <span
                                class="text-rom-slate decoration-rom-slate/40 group-hover:text-rom-slate-700 text-sm break-all underline underline-offset-4"
                                >{{ screenshot.filename }}</span
                            >
                            <span class="text-muted-foreground text-xs">{{ sizeText(screenshot.sizeBytes) }}</span>
                        </a>
                    </li>
                </ul>
            </section>

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
                        <p class="text-sm">
                            <span class="text-rom-ink font-semibold">{{ comment.testerName }}</span>
                            <span class="text-muted-foreground">
                                · <time :datetime="comment.createdAt">{{ formatDate(comment.createdAt) }}</time></span
                            >
                        </p>
                        <p class="text-rom-ink text-base break-words whitespace-pre-line">{{ comment.body }}</p>
                    </li>
                </ol>
                <p v-else class="text-muted-foreground text-sm">{{ trans('feedback.comments.empty') }}</p>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
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
                            required
                            maxlength="5000"
                            :rows="4"
                            :aria-invalid="form.errors.body ? true : undefined"
                        />
                        <InputError :message="form.errors.body" />
                    </div>
                    <div>
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('feedback.comments.add') }}</Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
