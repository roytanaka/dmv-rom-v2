<script setup lang="ts">
// DMV Settings › Groups (#841, ADR-0036 §3; restyled in #848): the whole org tree at a glance,
// every Group under its parent in display order, archived Groups included and marked. Super-tier
// only. A row's name is its one action, opening the structure form; the root and Containers have
// no form here. Group names are content, shown as stored. Create opens the CreateGroupDialog (#842).
//
// The tree is a list, so it never scrolls sideways (docs/conventions.md § Tables on small
// screens): below `md` the Kind, Capabilities and Status columns hide and their facts ride under
// the name; below `sm` a stretched link makes the whole row one tap. The toolbar filters on the
// client: a name search and the "Show archived Groups" tick. Every row with children can collapse
// its subtree; all start expanded. A search ignores collapse so a match never hides.
//
// A parent officer (#883, ADR-0036 §11) gets a `scoped` page: only the subtrees below the Groups
// they administer, each headed by their own Group (`head`, listed but not opened), and no
// Stewardships section. Their Create button opens the CreateSubGroupDialog instead (#884): name
// and a parent from `subGroupParents`, nothing else.
import CreateGroupDialog from '@/components/CreateGroupDialog.vue';
import CreateSubGroupDialog, { type SubGroupParent } from '@/components/CreateSubGroupDialog.vue';
import DmvSettingsTabs from '@/components/DmvSettingsTabs.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type ParentOption } from '@/composables/useParentPicker';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PhCaretDown, PhCaretRight, PhMagnifyingGlass, PhPlus } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

// The six capability slots, in the order the row shows them.
const CAPABILITIES = ['has_meetings', 'has_documents', 'has_scheduling', 'has_vetting', 'has_announcements', 'has_bookings'] as const;
type Capability = (typeof CAPABILITIES)[number];

interface TreeRow {
    id: number;
    slug: string;
    name: string;
    depth: number;
    kind: string;
    archived: boolean;
    endedOn: string | null;
    editable: boolean;
    head: boolean;
    href: string | null;
    capabilities: Record<Capability, boolean>;
    stewardships: string[];
    hoursMultiplier: number;
}

const props = defineProps<{
    scoped: boolean;
    tree: TreeRow[];
    parentOptions: ParentOption[];
    kinds: string[];
    subGroupParents: SubGroupParent[];
    groupsHref: string;
    stewardshipsHref: string;
}>();

const createOpen = ref(false);
const search = ref('');
const showArchived = ref(true);
// The Groups whose subtree is folded away.
const collapsed = ref(new Set<number>());

const page = usePage<SharedData>();

// `computed` so the label survives a full-page locale switch (the messages load async).
const title = computed(() => trans('dmv_settings.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: props.groupsHref }]);

// A parent officer creates a sub-group, and only while some active Group can take one.
const canCreate = computed(() => !props.scoped || props.subGroupParents.length > 0);
const createLabel = computed(() => trans(props.scoped ? 'dmv_settings.tree.create_sub_group' : 'dmv_settings.tree.create'));

// A row has children when the next row sits deeper (the tree is depth-first).
const hasChildren = computed(() => new Map(props.tree.map((row, index) => [row.id, (props.tree[index + 1]?.depth ?? 0) > row.depth])));

const toggle = (row: TreeRow): void => {
    const next = new Set(collapsed.value);
    if (!next.delete(row.id)) next.add(row.id);
    collapsed.value = next;
};

// Case- and accent-insensitive, so "reunion" finds "Réunion".
const normalize = (text: string): string => text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

// One walk decides every row. A hidden archived Group hides its subtree; so does a collapsed
// one. A search keeps each name match with its ancestors, and ignores collapse.
const visibleRows = computed<TreeRow[]>(() => {
    const query = normalize(search.value.trim());
    const shown: TreeRow[] = [];
    const kept = new Set<number>();
    const lineage: TreeRow[] = [];
    let prunedAt: number | null = null;

    for (const row of props.tree) {
        lineage.splice(row.depth);
        lineage.push(row);
        if (prunedAt !== null && row.depth > prunedAt) continue;
        prunedAt = null;
        if (!showArchived.value && row.archived) {
            prunedAt = row.depth;
            continue;
        }
        if (query !== '') {
            if (normalize(row.name).includes(query)) lineage.forEach((ancestor) => kept.add(ancestor.id));
            continue;
        }
        shown.push(row);
        if (collapsed.value.has(row.id)) prunedAt = row.depth;
    }

    return query === '' ? shown : props.tree.filter((row) => kept.has(row.id));
});

const kindLabel = (row: TreeRow): string => trans(`dmv_settings.kind.${row.kind}`);
const capabilityLabel = (flag: Capability): string => trans(`dmv_settings.capability.${flag}`);
const capabilityName = (row: TreeRow, flag: Capability): string =>
    trans(row.capabilities[flag] ? 'dmv_settings.tree.capability_on' : 'dmv_settings.tree.capability_off', { capability: capabilityLabel(flag) });
const legend = computed(() => CAPABILITIES.map((flag) => `${trans(`dmv_settings.tree.letter.${flag}`)} ${capabilityLabel(flag)}`).join(' · '));

// Archived takes precedence over an ended time-box, as on the Group page.
const badge = (row: TreeRow): { variant: 'secondary' | 'warning'; label: string } | null => {
    if (row.archived) return { variant: 'secondary', label: trans('dmv_settings.tree.archived') };
    if (row.endedOn) return { variant: 'warning', label: trans('group.ended', { date: formatDateOnly(row.endedOn, page.props.locale) }) };
    return null;
};

// The quiet facts: the Stewardships the Group holds, and its hours multiplier when it is not 1.
const number = computed(() => new Intl.NumberFormat(page.props.locale, { maximumFractionDigits: 3 }));
const facts = (row: TreeRow): string[] => [
    ...row.stewardships.map((fn) => trans(`dmv_settings.tree.stewardship.${fn}`)),
    ...(row.hoursMultiplier === 1 ? [] : [trans('dmv_settings.tree.hours', { multiplier: number.value.format(row.hoursMultiplier) })]),
];

// Why a row has no link: a parent officer's own Group is theirs to run, not to restructure; the
// root is set by officers; a Container has no page.
const note = (row: TreeRow): string =>
    trans(row.head ? 'dmv_settings.tree.own_note' : row.kind === 'container' ? 'dmv_settings.tree.container_note' : 'dmv_settings.tree.root_note');

// Indent per depth (0.75rem a level on a phone, 1.25rem from sm), via a CSS variable the cell reads.
const indent = (row: TreeRow) => ({ '--indent': `${row.depth}` });
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ trans(scoped ? 'dmv_settings.subtitle_scoped' : 'dmv_settings.subtitle') }}</p>
                </div>
                <Button v-if="canCreate" class="hidden sm:inline-flex" @click="createOpen = true">{{ createLabel }}</Button>
            </div>

            <DmvSettingsTabs :groups-href="groupsHref" :stewardships-href="stewardshipsHref" :scoped="scoped" />

            <div class="flex flex-col gap-3">
                <!-- The toolbar: a per-screen composition above the Table primitive. Below sm the
                     Create button becomes an icon beside the search field. -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative min-w-0 flex-1 sm:max-w-xs sm:flex-none sm:basis-80">
                        <PhMagnifyingGlass class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            v-model="search"
                            type="search"
                            :placeholder="trans('dmv_settings.tree.search')"
                            :aria-label="trans('dmv_settings.tree.search')"
                            class="pl-9"
                        />
                    </div>
                    <Button v-if="canCreate" size="icon" class="sm:hidden" :aria-label="createLabel" @click="createOpen = true">
                        <PhPlus />
                    </Button>
                    <label class="flex w-full items-center gap-2 text-sm sm:w-auto pointer-coarse:min-h-11">
                        <Checkbox :checked="showArchived" @update:checked="(on: boolean) => (showArchived = on)" />
                        {{ trans('dmv_settings.tree.show_archived') }}
                    </label>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ trans('dmv_settings.tree.column.name') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ trans('dmv_settings.tree.column.kind') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ trans('dmv_settings.tree.column.capabilities') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ trans('dmv_settings.tree.column.status') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in visibleRows" :key="row.id" :aria-level="row.depth + 1">
                            <TableCell
                                class="relative pl-[calc(0.75rem+var(--indent)*0.75rem)] whitespace-normal sm:pl-[calc(0.75rem+var(--indent)*1.25rem)]"
                                :style="indent(row)"
                            >
                                <div class="flex items-start gap-1">
                                    <!-- The fold toggle, above the phone's stretched link. A row with
                                         no children keeps the slot so names line up. -->
                                    <button
                                        v-if="hasChildren.get(row.id)"
                                        type="button"
                                        class="text-muted-foreground hover:text-rom-ink relative z-10 -my-1 flex size-7 shrink-0 items-center justify-center pointer-coarse:-my-2 pointer-coarse:size-11"
                                        :aria-label="
                                            trans(collapsed.has(row.id) ? 'dmv_settings.tree.expand' : 'dmv_settings.tree.collapse', {
                                                name: row.name,
                                            })
                                        "
                                        :aria-expanded="!collapsed.has(row.id)"
                                        @click="toggle(row)"
                                    >
                                        <PhCaretRight v-if="collapsed.has(row.id)" class="size-4" />
                                        <PhCaretDown v-else class="size-4" />
                                    </button>
                                    <span v-else class="-my-1 size-7 shrink-0 pointer-coarse:-my-2 pointer-coarse:size-11" aria-hidden="true" />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-baseline gap-x-3">
                                            <TextLink v-if="row.href" :href="row.href">
                                                <span :class="row.archived ? 'text-muted-foreground' : undefined">{{ row.name }}</span>
                                            </TextLink>
                                            <template v-else>
                                                <span>{{ row.name }}</span>
                                                <span class="text-muted-foreground text-sm">{{ note(row) }}</span>
                                            </template>
                                        </div>
                                        <!-- Below md the Kind and Status columns hide; their facts ride under the name. -->
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 md:hidden">
                                            <span class="text-muted-foreground text-sm">{{ [kindLabel(row), ...facts(row)].join(' · ') }}</span>
                                            <Badge v-if="badge(row)" :variant="badge(row)!.variant">{{ badge(row)!.label }}</Badge>
                                        </div>
                                    </div>

                                    <PhCaretRight v-if="row.href" class="text-muted-foreground mt-1 size-5 shrink-0 sm:hidden" aria-hidden="true" />
                                </div>
                                <!-- Below sm the whole row is one tap: a stretched twin of the name link,
                                     out of the tab order so the name stays the one link a reader meets. -->
                                <Link v-if="row.href" :href="row.href" class="absolute inset-0 sm:hidden" tabindex="-1" aria-hidden="true" />
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                <Badge variant="outline">{{ kindLabel(row) }}</Badge>
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                <div class="flex gap-1">
                                    <span
                                        v-for="flag in CAPABILITIES"
                                        :key="flag"
                                        role="img"
                                        :aria-label="capabilityName(row, flag)"
                                        class="inline-flex size-7 items-center justify-center text-sm font-semibold"
                                        :class="row.capabilities[flag] ? 'bg-muted text-rom-slate' : 'text-muted-foreground/40'"
                                    >
                                        {{ trans(`dmv_settings.tree.letter.${flag}`) }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                <Badge v-if="badge(row)" :variant="badge(row)!.variant">{{ badge(row)!.label }}</Badge>
                                <span v-else-if="facts(row).length > 0" class="text-muted-foreground text-sm">{{ facts(row).join(' · ') }}</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p class="text-muted-foreground text-sm">{{ legend }}</p>
            </div>
        </div>

        <CreateSubGroupDialog v-if="scoped" v-model:open="createOpen" :parents="subGroupParents" />
        <CreateGroupDialog v-else v-model:open="createOpen" :parent-options="parentOptions" :kinds="kinds" />
    </AppLayout>
</template>
