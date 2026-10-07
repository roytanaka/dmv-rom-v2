<script setup lang="ts">
// One Folder's manage menu on the Group Documents tab (#714, ADR-0030 §3): rename (edit, with
// who can read it, for a top-level Folder, #715), move, or delete after a confirm. Rendered only for a manager; the DocumentFolderPolicy enforces every
// write. A Folder that still holds Folders or Documents is refused by the server, and the
// confirm shows that message.
import DocumentFolderDialog from '@/components/DocumentFolderDialog.vue';
import DocumentMoveDialog from '@/components/DocumentMoveDialog.vue';
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type FolderDestination, type LibraryFolder } from '@/types';
import { router } from '@inertiajs/vue3';
import { PhArrowBendUpRight, PhDotsThreeVertical, PhPencilSimple, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

defineProps<{
    folder: LibraryFolder;
    /** The open Folder this one sits in, or null at the top level. */
    parentId: number | null;
    destinations: FolderDestination[];
    maxDepth: number;
}>();

const renaming = ref(false);
const moving = ref(false);
const deleting = ref(false);
const deleteBusy = ref(false);
const deleteError = ref<string | null>(null);

watch(deleting, (isOpen) => {
    if (isOpen) deleteError.value = null;
});

function confirmDelete(id: number): void {
    router.delete(route('document-folders.destroy', { folder: id }), {
        preserveScroll: true,
        onStart: () => (deleteBusy.value = true),
        onSuccess: () => (deleting.value = false),
        onError: (errors) => (deleteError.value = errors.folder ?? Object.values(errors)[0] ?? null),
        onFinish: () => (deleteBusy.value = false),
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :aria-label="trans('document_folders.actions', { name: folder.name })">
                <PhDotsThreeVertical class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem class="gap-2" @select="renaming = true">
                <PhPencilSimple class="size-4" />
                {{ trans(parentId === null ? 'document_folders.edit' : 'document_folders.rename') }}
            </DropdownMenuItem>
            <DropdownMenuItem class="gap-2" @select="moving = true">
                <PhArrowBendUpRight class="size-4" />
                {{ trans('document_folders.move') }}
            </DropdownMenuItem>
            <DropdownMenuItem class="text-destructive gap-2" @select="deleting = true">
                <PhTrash class="size-4" />
                {{ trans('document_folders.delete') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <DocumentFolderDialog v-model:open="renaming" :folder="folder" :top-level="parentId === null" />

    <DocumentMoveDialog
        v-model:open="moving"
        kind="folder"
        :id="folder.id"
        :name="folder.name"
        :current-folder-id="parentId"
        :destinations="destinations"
        :max-depth="maxDepth"
    />

    <AlertDialog v-model:open="deleting">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('document_folders.delete_title', { name: folder.name }) }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('document_folders.delete_body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <p v-if="deleteError" role="alert" class="text-destructive text-sm">{{ deleteError }}</p>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="deleteBusy">{{ trans('document_folders.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/80"
                    :disabled="deleteBusy"
                    @click.prevent="confirmDelete(folder.id)"
                >
                    {{ trans('document_folders.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
