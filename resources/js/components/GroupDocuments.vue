<script setup lang="ts">
// Group Documents tab (#712, spec #290, ADR-0030) — the Group's Document library. Lists the
// Documents at the library root the viewer may read: name (title, or the filename when there
// is none), type, size and update date, plus the uploader and upload date for a manager. Each
// name links to the gated download route.
//
// A manager (the Librarian, the Chair, or the super-tier; the server's `canManage` hint) gets
// the toolbar beside the Category filter: Manage categories, Add document, Add link and New
// folder (#755). Add document opens DocumentUploadDialog. Titles and filenames are content,
// shown as written (ADR-0004); everything else is translated chrome.
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
// one plain list with no heading. A manager keeps the open Folder's list from the Manage
// categories button (DocumentCategoriesDialog) and files an item from its Edit dialog. The
// Category filter beside the breadcrumb (#725, DocumentCategoryFilter) narrows the page to one
// section. New Folders, links and uploads are filed on the way in (#728).
//
// Image Documents (#779): a click on a PNG, JPEG, WebP or GIF name opens the ImageViewer over
// the page; its Download button saves the file through the same gated, logged route.
//
// Where am I (#726): the root shows a breadcrumb with one current item, Documents; an open
// Folder adds DocumentFolderHeader (icon, name, what it holds, who reads it).
import DocumentCategoriesDialog from '@/components/DocumentCategoriesDialog.vue';
import DocumentCategoryFilter from '@/components/DocumentCategoryFilter.vue';
import DocumentFolderBreadcrumb from '@/components/DocumentFolderBreadcrumb.vue';
import DocumentFolderDialog from '@/components/DocumentFolderDialog.vue';
import DocumentFolderHeader from '@/components/DocumentFolderHeader.vue';
import DocumentSection from '@/components/DocumentSection.vue';
import DocumentUploadDialog from '@/components/DocumentUploadDialog.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import LinkDocumentDialog from '@/components/LinkDocumentDialog.vue';
import { Button } from '@/components/ui/button';
import { libraryViewerEntries } from '@/documents/viewerEntries';
import { type GroupLibrary } from '@/types';
import { PhFolderPlus, PhLink, PhListBullets, PhUploadSimple } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

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

const uploadDialogOpen = ref(false);

// --- Image viewer (#779) -------------------------------------------------------------------

// Every file Document on screen, across the sections, so previous/next (#781) can step
// through the whole list. A click on an image Document opens the viewer at its entry.
const viewerEntries = computed(() => libraryViewerEntries(props.library.sections));
const viewerOpen = ref(false);
const viewerStart = ref(0);

function openViewer(documentId: number): void {
    const index = viewerEntries.value.findIndex((entry) => entry.key === documentId);
    if (index === -1) return;
    viewerStart.value = index;
    viewerOpen.value = true;
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <DocumentFolderBreadcrumb :group-slug="groupSlug" :folder="library.folder" :ancestors="library.breadcrumb" />

                <!-- The Category filter (#725), then a manager's actions (#755), beside the breadcrumb. They wrap under it on a phone. -->
                <div class="flex flex-wrap items-center gap-2">
                    <DocumentCategoryFilter :categories="library.categories" :category="library.category" />
                    <template v-if="canManage">
                        <Button type="button" variant="outline" size="sm" @click="categoriesDialogOpen = true">
                            <PhListBullets class="h-4 w-4" aria-hidden="true" />
                            {{ trans('document_categories.manage') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" @click="uploadDialogOpen = true">
                            <PhUploadSimple class="h-4 w-4" aria-hidden="true" />
                            {{ trans('documents.upload.add') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" @click="linkDialogOpen = true">
                            <PhLink class="h-4 w-4" aria-hidden="true" />
                            {{ trans('documents.link.add') }}
                        </Button>
                        <Button v-if="canAddFolder" type="button" variant="outline" size="sm" @click="folderDialogOpen = true">
                            <PhFolderPlus class="h-4 w-4" aria-hidden="true" />
                            {{ trans('document_folders.new') }}
                        </Button>
                    </template>
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
                @view="openViewer"
            />
        </template>

        <ImageViewer v-model:open="viewerOpen" :entries="viewerEntries" :start-index="viewerStart" />

        <DocumentCategoriesDialog
            v-if="canManage"
            v-model:open="categoriesDialogOpen"
            :group-slug="groupSlug"
            :folder-id="folderId"
            :categories="library.categories"
        />
        <DocumentUploadDialog
            v-if="canManage"
            v-model:open="uploadDialogOpen"
            :group-slug="groupSlug"
            :folder-id="folderId"
            :categories="library.categories"
            :filter="library.category"
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
