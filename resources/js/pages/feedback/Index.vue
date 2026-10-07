<script setup lang="ts">
// The Feedback page (#676, ADR-0029 §13): every Feedback item, newest first, from every
// Tester. Each row links to the item's page (#677). A muted `#N` leads each row and a
// chat icon shows the comment count (#701). Testers filter by type and by status
// (#679), or by Open or Closed status; the filters are query parameters, so a filtered list has a URL. Outside
// production only. Type and status labels are chrome (the lang keys come from the enums);
// the Tester's name and message are content, shown as sent.
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectSeparator, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatFeedbackDate, STATUS_TONES, type FeedbackOption } from '@/feedback/display';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { PhChatCircle } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface FeedbackRow {
    id: number;
    typeLabelKey: string;
    status: string;
    statusLabelKey: string;
    testerName: string;
    excerpt: string;
    commentsCount: number;
    createdAt: string;
    href: string;
}

interface FeedbackFilters {
    type: string | null;
    status: string | null;
}

const props = defineProps<{
    items: FeedbackRow[];
    filters: FeedbackFilters;
    types: FeedbackOption[];
    statuses: FeedbackOption[];
    listHref: string;
}>();

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('feedback.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: page.url }]);

const formatDate = (iso: string): string => formatFeedbackDate(iso, page.props.locale, page.props.timezone);

// A Select item cannot hold an empty value, so "All" is its own value, never sent.
const ALL = 'all';

const filtered = computed(() => props.filters.type !== null || props.filters.status !== null);

function applyFilters(filters: FeedbackFilters): void {
    const query: Record<string, string> = {};

    if (filters.type !== null) query.type = filters.type;
    if (filters.status !== null) query.status = filters.status;

    router.get(props.listHref, query, { preserveState: true, preserveScroll: true, replace: true });
}

const typeFilter = computed({
    get: () => props.filters.type ?? ALL,
    set: (value: string) => applyFilters({ ...props.filters, type: value === ALL ? null : value }),
});

const statusFilter = computed({
    get: () => props.filters.status ?? ALL,
    set: (value: string) => applyFilters({ ...props.filters, status: value === ALL ? null : value }),
});

const clearFilters = (): void => applyFilters({ type: null, status: null });
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>

            <div class="flex flex-wrap items-end gap-4">
                <div class="grid gap-2">
                    <Label for="feedback-filter-type">{{ trans('feedback.filter.type') }}</Label>
                    <Select v-model="typeFilter">
                        <SelectTrigger id="feedback-filter-type" class="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">{{ trans('feedback.filter.all_types') }}</SelectItem>
                            <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ trans(type.labelKey) }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-filter-status">{{ trans('feedback.filter.status') }}</Label>
                    <Select v-model="statusFilter">
                        <SelectTrigger id="feedback-filter-status" class="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">{{ trans('feedback.filter.all_statuses') }}</SelectItem>
                            <SelectItem value="open">{{ trans('feedback.filter.open') }}</SelectItem>
                            <SelectItem value="closed">{{ trans('feedback.filter.closed') }}</SelectItem>
                            <SelectSeparator />
                            <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ trans(status.labelKey) }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button v-if="filtered" type="button" variant="link" @click="clearFilters">{{ trans('feedback.filter.clear') }}</Button>
            </div>

            <div v-if="items.length" class="overflow-x-auto border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-px" />
                            <TableHead>{{ trans('feedback.column.message') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.type') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.status') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.name') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.date') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.comments') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell class="text-muted-foreground text-xs whitespace-nowrap tabular-nums">#{{ item.id }}</TableCell>
                            <TableCell class="min-w-64">
                                <TextLink :href="item.href">{{ item.excerpt }}</TextLink>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">{{ trans(item.typeLabelKey) }}</TableCell>
                            <TableCell>
                                <Badge :variant="STATUS_TONES[item.status]">{{ trans(item.statusLabelKey) }}</Badge>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">{{ item.testerName }}</TableCell>
                            <TableCell class="whitespace-nowrap">{{ formatDate(item.createdAt) }}</TableCell>
                            <TableCell class="text-muted-foreground whitespace-nowrap">
                                <span
                                    v-if="item.commentsCount > 0"
                                    class="inline-flex items-center gap-1 tabular-nums"
                                    :aria-label="
                                        transChoice('feedback.column.comments_count', item.commentsCount, { count: String(item.commentsCount) })
                                    "
                                >
                                    <PhChatCircle aria-hidden="true" class="size-4" />
                                    <span aria-hidden="true">{{ item.commentsCount }}</span>
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p v-else class="text-muted-foreground py-12 text-center text-base">
                {{ trans(filtered ? 'feedback.filter.empty' : 'feedback.empty') }}
            </p>
        </div>
    </AppLayout>
</template>
