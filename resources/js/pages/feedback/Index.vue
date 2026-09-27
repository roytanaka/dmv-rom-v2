<script setup lang="ts">
// The Feedback page (#676, ADR-0029 §13): every Feedback item, newest first, from every
// Tester. Outside production only. Type and status labels are chrome (the lang keys come
// from the enums); the Tester's name and message are content, shown as sent.
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface FeedbackRow {
    id: number;
    typeLabelKey: string;
    status: string;
    statusLabelKey: string;
    testerName: string;
    excerpt: string;
    createdAt: string;
}

defineProps<{
    items: FeedbackRow[];
}>();

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('feedback.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: page.url }]);

// Status tones (ADR-0029 design): New info, Confirmed warning, Fixed success, Won't fix
// secondary, Duplicate outline.
const STATUS_TONES: Record<string, BadgeVariants['variant']> = {
    new: 'info',
    confirmed: 'warning',
    fixed: 'success',
    'wont-fix': 'secondary',
    duplicate: 'outline',
};

// Sent dates read in the org timezone, like every other instant in the app.
const formatDate = (iso: string): string =>
    new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: page.props.timezone }).format(new Date(iso));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>

            <div v-if="items.length" class="overflow-x-auto border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ trans('feedback.column.type') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.status') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.name') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.message') }}</TableHead>
                            <TableHead>{{ trans('feedback.column.date') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell class="whitespace-nowrap">{{ trans(item.typeLabelKey) }}</TableCell>
                            <TableCell>
                                <Badge :variant="STATUS_TONES[item.status]">{{ trans(item.statusLabelKey) }}</Badge>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">{{ item.testerName }}</TableCell>
                            <TableCell class="min-w-64">{{ item.excerpt }}</TableCell>
                            <TableCell class="whitespace-nowrap">{{ formatDate(item.createdAt) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('feedback.empty') }}</p>
        </div>
    </AppLayout>
</template>
