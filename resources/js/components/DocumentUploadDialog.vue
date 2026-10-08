<script setup lang="ts">
// Add document (#755, spec #290, ADR-0030): upload files into the open Folder. A multi-file pick
// goes up one file per request, one after another, so each file shows its own progress bar and
// its own error. The DocumentPolicy enforces every upload regardless of what renders.
//
// One Document category covers the batch (#728, ADR-0030 §4). It starts at the active Category
// filter (defaultUploadCategory) each time the dialog opens.
//
// The dialog stays open after the uploads finish, so the Librarian sees each file's result. A
// close while files still upload or wait (close button, Escape, a click outside) asks first:
// Keep uploading, or Cancel uploads. Cancel aborts the file in progress, drops the waiting ones
// and closes. Files already uploaded stay.
import DocumentCategorySelect from '@/components/DocumentCategorySelect.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { defaultUploadCategory } from '@/documents/uploadCategory';
import { type LibraryCategory } from '@/types';
import { router } from '@inertiajs/vue3';
import { PhCheckCircle, PhFile, PhUploadSimple, PhWarningCircle } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

// `folderId`: the open Folder the files land in (#714); null at the library root.
// `categories`: that Folder's Document categories. `filter`: the active Category filter (#725).
const props = defineProps<{ groupSlug: string; folderId: number | null; categories: LibraryCategory[]; filter: number | null }>();
const open = defineModel<boolean>('open', { required: true });

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
const uploadCategory = ref<number | null>(null);
const confirmOpen = ref(false);
let cancelToken: { cancel: () => void } | null = null;
let nextId = 0;

// Start fresh each time the dialog opens: no old results, the category at the active filter.
watch(open, (isOpen) => {
    if (!isOpen) return;
    uploads.value = [];
    dragging.value = false;
    uploadCategory.value = defaultUploadCategory(props.categories, props.filter);
});

// Every close goes through here, so the alert guards the close button, Escape and a click outside.
function onOpenChange(value: boolean): void {
    if (!value && uploading.value) {
        confirmOpen.value = true;
        return;
    }
    open.value = value;
}

// Drop the waiting files first, so the queue stops once the cancelled request finishes.
function cancelUploads(): void {
    uploads.value = uploads.value.filter((upload) => upload.state !== 'waiting');
    cancelToken?.cancel();
    confirmOpen.value = false;
    open.value = false;
}

// One request per file (no chunking, ADR-0030 §12). Inertia runs one visit at a time, so the
// files go up in turn; each visit's progress events drive that file's bar.
function send(upload: Upload): Promise<void> {
    return new Promise((resolve) => {
        upload.state = 'uploading';
        router.post(
            route('documents.store', { group: props.groupSlug }),
            { file: upload.file, folder_id: props.folderId, category_id: upload.categoryId },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onCancelToken: (token) => (cancelToken = token),
                onProgress: (event) => (upload.progress = event?.percentage ?? upload.progress),
                onSuccess: () => {
                    upload.state = 'done';
                    upload.progress = 100;
                },
                onError: (errors) => {
                    upload.state = 'failed';
                    upload.error = errors.file ?? Object.values(errors)[0] ?? null;
                },
                onFinish: () => {
                    cancelToken = null;
                    resolve();
                },
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
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('documents.upload.title') }}</DialogTitle>
            </DialogHeader>

            <!-- The upload's Document category (#728), picked before the files go up. -->
            <DocumentCategorySelect id="upload-category" v-model="uploadCategory" :categories="categories" />

            <!-- The whole zone takes a drop; the button opens the picker. -->
            <div
                class="flex flex-col items-center justify-center gap-2 border-2 border-dashed px-4 py-6 text-center text-sm"
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
        </DialogContent>
    </Dialog>

    <!-- Keep uploading is the cancel button, so it takes the focus when the alert opens. -->
    <AlertDialog v-model:open="confirmOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('documents.upload.confirm_close.title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('documents.upload.confirm_close.body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ trans('documents.upload.confirm_close.keep') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/80" @click.prevent="cancelUploads">
                    {{ trans('documents.upload.confirm_close.cancel') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
