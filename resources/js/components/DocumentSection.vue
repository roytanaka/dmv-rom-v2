<script setup lang="ts">
// One section of the Group Documents tab (#724, spec #721, ADR-0030 §4): a Document category's
// Folders and files, or Other's. Headed (a heavy rule, an icon, the name and a count) only
// when the open Folder has Document categories; a Folder without any shows one plain list.
// The heading reads apart from the files table header below it.
//
// Rows as on the Folder page (#712, #714): Folders first, each a link into it, then files with
// name, type, size and update date, plus the uploader and upload date for a manager. Each name
// links to the gated download route. A link Document (#716) shows a link icon in place of type
// and size. A manager gets each row's actions menu. Names are content, shown as written
// (ADR-0004).
import DocumentActions from '@/components/DocumentActions.vue';
import DocumentFolderActions from '@/components/DocumentFolderActions.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type FolderDestination, type LibraryCategory, type LibrarySection, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { PhFolder, PhLink, PhStack } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    section: LibrarySection;
    /** Show the heading: the open Folder has Document categories. */
    headed: boolean;
    canManage: boolean;
    /** The open Folder, or null at the library root. */
    folderId: number | null;
    /** The open Folder's Document categories, for the rows' Edit dialogs. */
    categories: LibraryCategory[];
    destinations: FolderDestination[];
    /** The library root's Document categories, for a move to the top level (#728). */
    rootCategories: LibraryCategory[];
    maxDepth: number;
}>();

const page = usePage<SharedData>();

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium', timeZone: page.props.timezone }).format(new Date(iso));

// A file size in the page's language: KB under a megabyte, MB under a gigabyte, then GB.
function formatSize(bytes: number | null): string {
    if (bytes === null) return '';
    const [unit, value] =
        bytes < 1024 ** 2
            ? (['kilobyte', bytes / 1024] as const)
            : bytes < 1024 ** 3
              ? (['megabyte', bytes / 1024 ** 2] as const)
              : (['gigabyte', bytes / 1024 ** 3] as const);

    return new Intl.NumberFormat(page.props.locale, { style: 'unit', unit, maximumFractionDigits: value < 10 ? 1 : 0 }).format(Math.max(value, 0.1));
}

const name = computed(() => props.section.category?.name ?? trans('document_categories.other'));
// "2 folders · 3 files": the Folders part only when there are some; the files part otherwise
// always, so an empty section reads "0 files".
const count = computed(() =>
    [
        props.section.folderCount > 0
            ? transChoice('document_categories.count.folders', props.section.folderCount, { count: String(props.section.folderCount) })
            : null,
        props.section.documentCount > 0 || props.section.folderCount === 0
            ? transChoice('document_categories.count.files', props.section.documentCount, { count: String(props.section.documentCount) })
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
const isEmpty = computed(() => props.section.folderCount + props.section.documentCount === 0);
const headingId = computed(() => `document-section-${props.section.category?.id ?? 'other'}`);
</script>

<template>
    <section class="flex flex-col gap-3" :aria-labelledby="headed ? headingId : undefined">
        <div v-if="headed" class="border-primary flex flex-wrap items-baseline gap-x-3 gap-y-1 border-t-4 pt-3">
            <h3 :id="headingId" class="text-rom-ink flex items-center gap-2 text-lg font-semibold">
                <PhStack class="h-5 w-5 shrink-0 self-center" aria-hidden="true" />
                {{ name }}
            </h3>
            <span class="text-muted-foreground text-sm">{{ count }}</span>
        </div>

        <Table v-if="!isEmpty">
            <TableHeader>
                <TableRow>
                    <TableHead>{{ trans('documents.column.name') }}</TableHead>
                    <TableHead class="hidden sm:table-cell">{{ trans('documents.column.type') }}</TableHead>
                    <TableHead class="hidden text-right sm:table-cell">{{ trans('documents.column.size') }}</TableHead>
                    <TableHead class="hidden md:table-cell">{{ trans('documents.column.updated') }}</TableHead>
                    <TableHead v-if="canManage" class="hidden lg:table-cell">{{ trans('documents.column.uploader') }}</TableHead>
                    <TableHead v-if="canManage" class="w-10"
                        ><span class="sr-only">{{ trans('documents.column.actions') }}</span></TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <!-- Folders first (#714), each a link into it. -->
                <TableRow v-for="folder in section.folders" :key="`folder-${folder.id}`">
                    <TableCell class="whitespace-normal">
                        <Link
                            :href="folder.href"
                            class="text-rom-ink inline-flex items-center gap-2 font-medium underline-offset-4 hover:underline"
                            :aria-label="trans('document_folders.open', { name: folder.name })"
                        >
                            <PhFolder class="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
                            {{ folder.name }}
                        </Link>
                        <p class="text-muted-foreground text-xs">
                            {{ trans('document_folders.readable_by', { who: trans(`document_folders.visibility.${folder.visibility}`) }) }}
                        </p>
                    </TableCell>
                    <TableCell class="text-muted-foreground hidden sm:table-cell">{{ trans('document_folders.kind') }}</TableCell>
                    <TableCell class="hidden sm:table-cell" />
                    <TableCell class="hidden md:table-cell" />
                    <TableCell v-if="canManage" class="hidden lg:table-cell" />
                    <TableCell v-if="canManage" class="w-10 text-right">
                        <DocumentFolderActions
                            :folder="folder"
                            :parent-id="folderId"
                            :categories="categories"
                            :destinations="destinations"
                            :root-categories="rootCategories"
                            :max-depth="maxDepth"
                        />
                    </TableCell>
                </TableRow>
                <TableRow v-for="document in section.documents" :key="document.id">
                    <TableCell class="whitespace-normal">
                        <a
                            v-if="document.kind === 'link'"
                            :href="document.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-rom-ink font-medium underline-offset-4 hover:underline"
                            :aria-label="trans('documents.link.open', { name: document.title ?? '' })"
                        >
                            {{ document.title }}
                        </a>
                        <a
                            v-else
                            :href="document.href"
                            class="text-rom-ink font-medium underline-offset-4 hover:underline"
                            :aria-label="trans('documents.download', { name: document.title ?? document.filename ?? '' })"
                        >
                            {{ document.title ?? document.filename }}
                        </a>
                        <p v-if="document.description" class="text-rom-ink text-sm whitespace-pre-line">{{ document.description }}</p>
                        <!-- On a phone the other columns hide; their facts ride under the name. -->
                        <p class="text-muted-foreground text-xs sm:hidden">
                            {{
                                [
                                    document.kind === 'link' ? trans('documents.link.type') : document.extension?.toUpperCase(),
                                    formatSize(document.sizeBytes),
                                    formatDate(document.updatedAt),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                    </TableCell>
                    <TableCell class="text-muted-foreground hidden sm:table-cell">
                        <PhLink v-if="document.kind === 'link'" class="h-4 w-4" :aria-label="trans('documents.link.type')" />
                        <template v-else>{{ document.extension?.toUpperCase() }}</template>
                    </TableCell>
                    <TableCell class="text-muted-foreground hidden text-right sm:table-cell">{{ formatSize(document.sizeBytes) }}</TableCell>
                    <TableCell class="text-muted-foreground hidden md:table-cell">{{ formatDate(document.updatedAt) }}</TableCell>
                    <TableCell v-if="canManage" class="text-muted-foreground hidden lg:table-cell">
                        {{ document.uploader }}
                        <p v-if="document.uploadedAt" class="text-xs">{{ formatDate(document.uploadedAt) }}</p>
                    </TableCell>
                    <TableCell v-if="canManage" class="w-10 text-right">
                        <DocumentActions
                            :document="document"
                            :categories="categories"
                            :destinations="destinations"
                            :root-categories="rootCategories"
                            :max-depth="maxDepth"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </section>
</template>
