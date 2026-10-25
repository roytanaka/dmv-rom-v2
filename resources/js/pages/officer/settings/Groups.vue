<script setup lang="ts">
// DMV Settings › Groups (#841, ADR-0036 §3): the whole org tree at a glance, every Group under its
// parent in display order, archived Groups included and marked. Super-tier only. No collapse: the
// org has a few dozen Groups and the super-tier wants the whole structure at once. A row's name is
// its one action, opening the structure form; the root and Containers have no form here. Group
// names are content, shown as stored. Create opens the CreateGroupDialog (#842).
import CreateGroupDialog, { type ParentOption } from '@/components/CreateGroupDialog.vue';
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface TreeRow {
    id: number;
    slug: string;
    name: string;
    depth: number;
    kind: string;
    archived: boolean;
    endedOn: string | null;
    editable: boolean;
    href: string | null;
}

const props = defineProps<{ tree: TreeRow[]; parentOptions: ParentOption[]; kinds: string[]; groupsHref: string; stewardshipsHref: string }>();

const createOpen = ref(false);

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: props.groupsHref }]);

const kindLabel = (row: TreeRow): string => trans(`dmv_settings.kind.${row.kind}`);

// Archived takes precedence over an ended time-box, as on the Group page.
const statusLabel = (row: TreeRow): string | null => {
    if (row.archived) return trans('dmv_settings.tree.archived');
    if (row.endedOn) return trans('group.ended', { date: formatDateOnly(row.endedOn, page.props.locale) });
    return null;
};

// Indent per depth (0.75rem a level on a phone, 1.25rem from sm), via a CSS variable the cell reads.
const indent = (row: TreeRow) => ({ '--indent': `${row.depth}` });
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

            <div class="flex flex-col gap-3">
                <div class="flex justify-end">
                    <Button size="sm" @click="createOpen = true">{{ trans('dmv_settings.tree.create') }}</Button>
                </div>
                <div class="border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ trans('dmv_settings.tree.column.name') }}</TableHead>
                                <TableHead class="hidden sm:table-cell">{{ trans('dmv_settings.tree.column.kind') }}</TableHead>
                                <TableHead class="hidden sm:table-cell">{{ trans('dmv_settings.tree.column.status') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in tree" :key="row.id" :aria-level="row.depth + 1">
                                <TableCell
                                    class="pl-[calc(0.75rem+var(--indent)*0.75rem)] whitespace-normal sm:pl-[calc(0.75rem+var(--indent)*1.25rem)]"
                                    :style="indent(row)"
                                >
                                    <div class="flex flex-wrap items-baseline gap-x-3">
                                        <TextLink v-if="row.editable && row.href" :href="row.href">
                                            <span :class="row.archived ? 'text-muted-foreground' : undefined">{{ row.name }}</span>
                                        </TextLink>
                                        <template v-else>
                                            <span>{{ row.name }}</span>
                                            <span class="text-muted-foreground text-sm">{{ trans('dmv_settings.tree.no_settings') }}</span>
                                        </template>
                                    </div>
                                    <!-- On a phone the Kind and Status columns hide; their facts ride under the name. -->
                                    <p class="text-muted-foreground text-sm sm:hidden">
                                        {{ [kindLabel(row), statusLabel(row)].filter(Boolean).join(' · ') }}
                                    </p>
                                </TableCell>
                                <TableCell class="hidden sm:table-cell">
                                    <Badge variant="outline">{{ kindLabel(row) }}</Badge>
                                </TableCell>
                                <TableCell class="hidden sm:table-cell">
                                    <Badge v-if="row.archived" variant="secondary">{{ statusLabel(row) }}</Badge>
                                    <Badge v-else-if="row.endedOn" variant="warning">{{ statusLabel(row) }}</Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <CreateGroupDialog v-model:open="createOpen" :parent-options="parentOptions" :kinds="kinds" />
    </AppLayout>
</template>
