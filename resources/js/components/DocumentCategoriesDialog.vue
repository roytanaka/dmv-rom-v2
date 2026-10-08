<script setup lang="ts">
// The Categories dialog of the Group Documents tab (#724, spec #721, ADR-0030 §4): the open
// Folder's (or the root's) Document categories, with add, rename and delete. Delete asks
// first; the server moves the Document category's items to Other. Rendered for a manager only;
// the Form Requests enforce every write and keep names unique within the list. Names are
// content, shown as written (ADR-0004).
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type LibraryCategory } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { PhPencilSimple, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

const props = defineProps<{
    groupSlug: string;
    /** The Folder whose list this is, or null for the library root. */
    folderId: number | null;
    categories: LibraryCategory[];
}>();
const open = defineModel<boolean>('open', { required: true });

// --- Add ------------------------------------------------------------------------------

const addForm = useForm({ name: '' });
addForm.transform((data) => ({ ...data, folder_id: props.folderId }));

function add(): void {
    addForm.post(route('document-categories.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

// --- Rename ---------------------------------------------------------------------------

const renamingId = ref<number | null>(null);
const renameForm = useForm({ name: '' });

function startRename(category: LibraryCategory): void {
    renameForm.name = category.name;
    renameForm.clearErrors();
    renamingId.value = category.id;
}

function saveRename(): void {
    renameForm.patch(route('document-categories.update', { category: renamingId.value }), {
        preserveScroll: true,
        onSuccess: () => (renamingId.value = null),
    });
}

// --- Delete ---------------------------------------------------------------------------

const deleting = ref<LibraryCategory | null>(null);
const deleteOpen = ref(false);
const deleteBusy = ref(false);

function askDelete(category: LibraryCategory): void {
    deleting.value = category;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) return;
    router.delete(route('document-categories.destroy', { category: deleting.value.id }), {
        preserveScroll: true,
        onStart: () => (deleteBusy.value = true),
        onFinish: () => {
            deleteBusy.value = false;
            deleteOpen.value = false;
        },
    });
}

watch(open, (isOpen) => {
    if (!isOpen) return;
    addForm.reset();
    addForm.clearErrors();
    renamingId.value = null;
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('document_categories.title') }}</DialogTitle>
            </DialogHeader>

            <p v-if="categories.length === 0" class="text-muted-foreground text-sm">{{ trans('document_categories.empty') }}</p>
            <ul v-else class="divide-border flex flex-col divide-y">
                <li v-for="category in categories" :key="category.id" class="py-2">
                    <form v-if="renamingId === category.id" class="flex flex-col gap-2" @submit.prevent="saveRename">
                        <Label :for="`category-${category.id}-name`" class="sr-only">{{ trans('document_categories.field.name') }}</Label>
                        <Input :id="`category-${category.id}-name`" v-model="renameForm.name" required maxlength="255" />
                        <InputError :message="renameForm.errors.name" />
                        <div class="flex gap-2">
                            <Button type="submit" size="sm" :disabled="renameForm.processing">{{ trans('document_categories.save') }}</Button>
                            <Button type="button" variant="ghost" size="sm" :disabled="renameForm.processing" @click="renamingId = null">
                                {{ trans('document_categories.cancel') }}
                            </Button>
                        </div>
                    </form>
                    <div v-else class="flex items-center gap-2">
                        <span class="min-w-0 flex-1 truncate">{{ category.name }}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="trans('document_categories.rename_label', { name: category.name })"
                            @click="startRename(category)"
                        >
                            <PhPencilSimple class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-destructive h-8 w-8"
                            :aria-label="trans('document_categories.delete_label', { name: category.name })"
                            @click="askDelete(category)"
                        >
                            <PhTrash class="size-4" />
                        </Button>
                    </div>
                </li>
            </ul>

            <form class="flex flex-col gap-2" @submit.prevent="add">
                <Label for="new-category-name">{{ trans('document_categories.field.new') }}</Label>
                <div class="flex gap-2">
                    <Input id="new-category-name" v-model="addForm.name" required maxlength="255" />
                    <Button type="submit" size="sm" :disabled="addForm.processing">{{ trans('document_categories.add') }}</Button>
                </div>
                <InputError :message="addForm.errors.name ?? (addForm.errors as Record<string, string | undefined>).folder_id" />
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('document_categories.delete_title', { name: deleting?.name ?? '' }) }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('document_categories.delete_body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="deleteBusy">{{ trans('document_categories.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/80"
                    :disabled="deleteBusy"
                    @click.prevent="confirmDelete"
                >
                    {{ trans('document_categories.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
