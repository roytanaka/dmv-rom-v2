<script setup lang="ts">
// Group Documents tab (#712, spec #290, ADR-0030) — the Group's Document library. Lists the
// Documents at the library root the viewer may read: name (title, or the filename when there
// is none), type, size and update date, plus the uploader and upload date for a manager. Each
// name links to the gated download route.
//
// A manager (the Librarian, the Chair, or the super-tier; the server's `canManage` hint) gets
// the upload drop zone. A multi-file pick goes up one file per request, one after another, so
// each file shows its own progress bar and its own error. The DocumentPolicy enforces every
// upload regardless of what renders. Titles and filenames are content, shown as written
// (ADR-0004); everything else is translated chrome.
//
// A link Document (#716) shows a link icon in place of type and size and opens in a new tab
// through the same gated route. A manager adds one in LinkDocumentDialog and edits it from
// the row's DocumentActions menu (#713).
//
// Folders (#714): the tab shows the open Folder (or the library root) with its breadcrumb, its
// child Folders above its Documents, and an empty-Folder message. Uploads and new links land
// in the open Folder. A manager creates Folders here and renames, moves or deletes one from
// its row's DocumentFolderActions menu.
//
// Visibility (#715, ADR-0030 §5): each Folder row and the open Folder say who can read them.
// A manager sets it on a top-level Folder in DocumentFolderDialog; the rest inherit.
import DocumentActions from '@/components/DocumentActions.vue';
import DocumentFolderActions from '@/components/DocumentFolderActions.vue';
import DocumentFolderBreadcrumb from '@/components/DocumentFolderBreadcrumb.vue';
import DocumentFolderDialog from '@/components/DocumentFolderDialog.vue';
import DocumentTagFilter from '@/components/DocumentTagFilter.vue';
import DocumentTagsEditor from '@/components/DocumentTagsEditor.vue';
import DocumentTagsManager from '@/components/DocumentTagsManager.vue';
import LinkDocumentDialog from '@/components/LinkDocumentDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type GroupLibrary, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { PhCheckCircle, PhFile, PhFolder, PhFolderPlus, PhLink, PhUploadSimple, PhWarningCircle } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ library: GroupLibrary; canManage: boolean; groupSlug: string }>();

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

// --- Link Documents (#716) -------------------------------------------------------------

const linkDialogOpen = ref(false);

// --- Folders (#714) --------------------------------------------------------------------

const folderDialogOpen = ref(false);
const folderId = computed(() => props.library.folder?.id ?? null);
// A new Folder goes inside the open one, so it fits only while the open one sits above the limit.
const canAddFolder = computed(() => props.library.breadcrumb.length + (props.library.folder ? 1 : 0) < props.library.maxDepth);
const isEmpty = computed(() => props.library.folders.length === 0 && props.library.documents.length === 0);
const emptyMessage = computed(() =>
    trans(props.library.tag ? 'document_tags.empty' : props.library.folder ? 'document_folders.empty' : 'documents.empty'),
);

// --- Upload ---------------------------------------------------------------------------

interface Upload {
    id: number;
    name: string;
    file: File;
    progress: number;
    state: 'waiting' | 'uploading' | 'done' | 'failed';
    error: string | null;
}

const uploads = ref<Upload[]>([]);
const uploading = ref(false);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
let nextId = 0;

// One request per file (no chunking, ADR-0030 §12). Inertia runs one visit at a time, so the
// files go up in turn; each visit's progress events drive that file's bar.
function send(upload: Upload): Promise<void> {
    return new Promise((resolve) => {
        upload.state = 'uploading';
        router.post(
            route('documents.store', { group: props.groupSlug }),
            { file: upload.file, folder_id: folderId.value },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onProgress: (event) => (upload.progress = event?.percentage ?? upload.progress),
                onSuccess: () => {
                    upload.state = 'done';
                    upload.progress = 100;
                },
                onError: (errors) => {
                    upload.state = 'failed';
                    upload.error = errors.file ?? Object.values(errors)[0] ?? null;
                },
                onFinish: () => resolve(),
            },
        );
    });
}

async function addFiles(files: File[]): Promise<void> {
    if (files.length === 0) return;

    const added = files.map((file) => ({ id: nextId++, name: file.name, file, progress: 0, state: 'waiting' as const, error: null }));
    uploads.value = [...uploads.value.filter((upload) => upload.state !== 'done'), ...added];

    if (uploading.value) return;

    uploading.value = true;
    let upload: Upload | undefined;
    while ((upload = uploads.value.find((item) => item.state === 'waiting'))) {
        await send(upload);
    }
    uploading.value = false;
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    addFiles(Array.from(input.files ?? []));
    input.value = '';
}

const dragsFiles = (event: DragEvent): boolean => event.dataTransfer?.types.includes('Files') ?? false;

function onDragOver(event: DragEvent): void {
    if (!dragsFiles(event)) return;
    event.preventDefault();
    dragging.value = true;
}

function onDrop(event: DragEvent): void {
    if (!dragsFiles(event)) return;
    event.preventDefault();
    dragging.value = false;
    addFiles(Array.from(event.dataTransfer?.files ?? []));
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="library.folder" class="flex flex-col gap-1">
            <DocumentFolderBreadcrumb :group-slug="groupSlug" :folder="library.folder" :ancestors="library.breadcrumb" />
            <p class="text-muted-foreground text-sm">
                {{ trans('document_folders.readable_by', { who: trans(`document_folders.visibility.${library.folder.visibility}`) }) }}
            </p>
        </div>

        <!-- Upload, for a manager only. The whole zone takes a drop; the button opens the picker. -->
        <section v-if="canManage" class="flex flex-col gap-3">
            <div
                class="flex flex-col items-center justify-center gap-2 border-2 border-dashed px-4 py-6 text-center text-sm sm:flex-row"
                :class="dragging ? 'border-rom-slate bg-muted' : 'border-border'"
                @dragover="onDragOver"
                @dragleave="dragging = false"
                @drop="onDrop"
            >
                <PhUploadSimple class="text-muted-foreground h-5 w-5" aria-hidden="true" />
                <span class="text-muted-foreground">{{ trans('documents.upload.drop') }}</span>
                <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                    {{ trans('documents.upload.choose') }}
                </Button>
                <input ref="fileInput" type="file" multiple class="sr-only" :aria-label="trans('documents.upload.button')" @change="onPick" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" @click="linkDialogOpen = true">
                    <PhLink class="h-4 w-4" aria-hidden="true" />
                    {{ trans('documents.link.add') }}
                </Button>
                <Button v-if="canAddFolder" type="button" variant="outline" size="sm" @click="folderDialogOpen = true">
                    <PhFolderPlus class="h-4 w-4" aria-hidden="true" />
                    {{ trans('document_folders.new') }}
                </Button>
            </div>

            <ul v-if="uploads.length" class="flex flex-col gap-2" aria-live="polite">
                <li v-for="upload in uploads" :key="upload.id" class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-sm">
                        <PhCheckCircle
                            v-if="upload.state === 'done'"
                            class="text-success h-4 w-4 shrink-0"
                            :aria-label="trans('documents.upload.done')"
                        />
                        <PhWarningCircle
                            v-else-if="upload.state === 'failed'"
                            class="text-destructive h-4 w-4 shrink-0"
                            :aria-label="trans('documents.upload.failed')"
                        />
                        <PhFile v-else class="text-muted-foreground h-4 w-4 shrink-0" :aria-label="trans('documents.upload.uploading')" />
                        <span class="truncate">{{ upload.name }}</span>
                    </div>
                    <Progress v-if="upload.state === 'uploading' || upload.state === 'waiting'" :model-value="upload.progress" class="h-1.5" />
                    <p v-if="upload.error" role="alert" class="text-destructive text-sm">{{ upload.error }}</p>
                </li>
            </ul>
        </section>

        <!-- Tags (#717): the one-Tag filter for readers once the Group has Tags; the Tag list for a manager. -->
        <div v-if="library.tags.length || canManage" class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
            <DocumentTagFilter v-if="library.tags.length" :tags="library.tags" :tag="library.tag" />
            <div v-if="canManage" class="sm:ml-auto"><DocumentTagsManager :tags="library.tags" :group-slug="groupSlug" /></div>
        </div>

        <p v-if="isEmpty" class="text-muted-foreground py-12 text-center text-base">{{ emptyMessage }}</p>

        <Table v-else>
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
                <TableRow v-for="folder in library.folders" :key="`folder-${folder.id}`">
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
                            :destinations="library.destinations"
                            :max-depth="library.maxDepth"
                        />
                    </TableCell>
                </TableRow>
                <TableRow v-for="document in library.documents" :key="document.id">
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
                        <span v-if="document.tags.length || canManage" class="mt-1 flex flex-wrap items-center gap-1">
                            <Badge v-for="tag in document.tags" :key="tag.id" variant="secondary">{{ tag.name }}</Badge>
                            <DocumentTagsEditor
                                v-if="canManage"
                                :document-id="document.id"
                                :name="document.title ?? document.filename ?? ''"
                                :tags="library.tags"
                                :selected="document.tags"
                            />
                        </span>
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
                        <DocumentActions :document="document" :destinations="library.destinations" :max-depth="library.maxDepth" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <LinkDocumentDialog v-if="canManage" v-model:open="linkDialogOpen" :group-slug="groupSlug" :folder-id="folderId" />
        <DocumentFolderDialog
            v-if="canManage"
            v-model:open="folderDialogOpen"
            :group-slug="groupSlug"
            :parent-id="folderId"
            :top-level="folderId === null"
        />
    </div>
</template>
