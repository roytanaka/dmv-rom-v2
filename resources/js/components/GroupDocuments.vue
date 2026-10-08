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
//
// Document categories (#724, ADR-0030 §4): the open Folder's items come in sections, one per
// Document category, then Other (DocumentSection). A Folder with no Document categories shows
// one plain list with no heading. A manager keeps the open Folder's list from the Categories
// button (DocumentCategoriesDialog) and files an item from its Edit dialog. The Category filter
// beside the breadcrumb (#725, DocumentCategoryFilter) narrows the page to one section. New
// Folders, links and uploads are filed on the way in (#728): the upload's Category select
// covers the whole batch and starts at the active filter.
//
// Where am I (#726): the root shows a breadcrumb with one current item, Documents; an open
// Folder adds DocumentFolderHeader (icon, name, what it holds, who reads it).
import DocumentCategoriesDialog from '@/components/DocumentCategoriesDialog.vue';
import DocumentCategoryFilter from '@/components/DocumentCategoryFilter.vue';
import DocumentCategorySelect from '@/components/DocumentCategorySelect.vue';
import DocumentFolderBreadcrumb from '@/components/DocumentFolderBreadcrumb.vue';
import DocumentFolderDialog from '@/components/DocumentFolderDialog.vue';
import DocumentFolderHeader from '@/components/DocumentFolderHeader.vue';
import DocumentSection from '@/components/DocumentSection.vue';
import LinkDocumentDialog from '@/components/LinkDocumentDialog.vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { type GroupLibrary } from '@/types';
import { router } from '@inertiajs/vue3';
import { PhCheckCircle, PhFile, PhFolderPlus, PhLink, PhListBullets, PhUploadSimple, PhWarningCircle } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ library: GroupLibrary; canManage: boolean; groupSlug: string }>();

// --- Link Documents (#716) -------------------------------------------------------------

const linkDialogOpen = ref(false);

// --- Folders (#714) --------------------------------------------------------------------

const folderDialogOpen = ref(false);
const folderId = computed(() => props.library.folder?.id ?? null);
// A new Folder goes inside the open one, so it fits only while the open one sits above the limit.
const canAddFolder = computed(() => props.library.breadcrumb.length + (props.library.folder ? 1 : 0) < props.library.maxDepth);
const isEmpty = computed(() => props.library.sections.length === 0);

// --- Document categories (#724) ---------------------------------------------------------

const categoriesDialogOpen = ref(false);
const emptyMessage = computed(() =>
    trans(props.library.category !== null ? 'document_categories.filter.empty' : props.library.folder ? 'document_folders.empty' : 'documents.empty'),
);

// --- Upload ---------------------------------------------------------------------------

interface Upload {
    id: number;
    name: string;
    file: File;
    /** The batch's Document category, fixed when the files were picked (#728). */
    categoryId: number | null;
    progress: number;
    state: 'waiting' | 'uploading' | 'done' | 'failed';
    error: string | null;
}

const uploads = ref<Upload[]>([]);
const uploading = ref(false);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
let nextId = 0;

// One Document category for the whole batch (#728, ADR-0030 §4). It starts at the active
// Category filter when that is one of the open Folder's, else "No category", and again on
// every Folder or filter change.
const uploadCategory = ref<number | null>(null);
watch(
    () => [props.library.category, folderId.value] as const,
    ([category]) => {
        uploadCategory.value = props.library.categories.some((item) => item.id === category) ? category : null;
    },
    { immediate: true },
);

// One request per file (no chunking, ADR-0030 §12). Inertia runs one visit at a time, so the
// files go up in turn; each visit's progress events drive that file's bar.
function send(upload: Upload): Promise<void> {
    return new Promise((resolve) => {
        upload.state = 'uploading';
        router.post(
            route('documents.store', { group: props.groupSlug }),
            { file: upload.file, folder_id: folderId.value, category_id: upload.categoryId },
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

    const added = files.map((file) => ({
        id: nextId++,
        name: file.name,
        file,
        categoryId: uploadCategory.value,
        progress: 0,
        state: 'waiting' as const,
        error: null,
    }));
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
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <DocumentFolderBreadcrumb :group-slug="groupSlug" :folder="library.folder" :ancestors="library.breadcrumb" />

                <!-- The Category filter (#725), and a manager's Categories button (#724), beside the breadcrumb. -->
                <div class="flex items-center gap-2">
                    <DocumentCategoryFilter :categories="library.categories" :category="library.category" />
                    <Button v-if="canManage" type="button" variant="outline" size="sm" @click="categoriesDialogOpen = true">
                        <PhListBullets class="h-4 w-4" aria-hidden="true" />
                        {{ trans('document_categories.manage') }}
                    </Button>
                </div>
            </div>
            <DocumentFolderHeader
                v-if="library.folder"
                :folder="library.folder"
                :category-count="library.categories.length"
                :folder-count="library.folderCount"
                :document-count="library.documentCount"
            />
        </div>

        <!-- Upload, for a manager only. The whole zone takes a drop; the button opens the picker. -->
        <section v-if="canManage" class="flex flex-col gap-3">
            <!-- The upload's Document category (#728), picked before the files go up. -->
            <DocumentCategorySelect id="upload-category" v-model="uploadCategory" :categories="library.categories" class="sm:max-w-xs" />
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

        <p v-if="isEmpty" class="text-muted-foreground py-12 text-center text-base">{{ emptyMessage }}</p>

        <template v-else>
            <DocumentSection
                v-for="section in library.sections"
                :key="section.category?.id ?? 'other'"
                :section="section"
                :headed="library.categories.length > 0"
                :can-manage="canManage"
                :folder-id="folderId"
                :categories="library.categories"
                :destinations="library.destinations"
                :root-categories="library.rootCategories"
                :max-depth="library.maxDepth"
            />
        </template>

        <DocumentCategoriesDialog
            v-if="canManage"
            v-model:open="categoriesDialogOpen"
            :group-slug="groupSlug"
            :folder-id="folderId"
            :categories="library.categories"
        />
        <LinkDocumentDialog
            v-if="canManage"
            v-model:open="linkDialogOpen"
            :group-slug="groupSlug"
            :folder-id="folderId"
            :categories="library.categories"
        />
        <DocumentFolderDialog
            v-if="canManage"
            v-model:open="folderDialogOpen"
            :group-slug="groupSlug"
            :parent-id="folderId"
            :top-level="folderId === null"
            :categories="library.categories"
        />
    </div>
</template>
