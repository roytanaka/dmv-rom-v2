<script setup lang="ts">
// One Document's manage menu on the Group Documents tab (#713, spec #290, ADR-0030 §8): edit
// its title and description, upload a new file over it, or delete it after a confirm. Rendered
// only for a manager (the server's `canManage` hint); the DocumentPolicy enforces every write.
// Title and description are content, kept as written (ADR-0004).
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import { type LibraryDocument } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { PhArrowsClockwise, PhDotsThreeVertical, PhPencilSimple, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ document: LibraryDocument }>();

const name = computed(() => props.document.title ?? props.document.filename ?? '');
const isFile = computed(() => props.document.filename !== null);

// --- Edit -----------------------------------------------------------------------------

const editing = ref(false);
const editForm = useForm({ title: '', description: '' });

function openEdit(): void {
    editForm.title = props.document.title ?? '';
    editForm.description = props.document.description ?? '';
    editForm.clearErrors();
    editing.value = true;
}

function saveEdit(): void {
    editForm.patch(route('documents.update', { document: props.document.id }), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

// --- Replace --------------------------------------------------------------------------

const replacing = ref(false);
const replaceForm = useForm<{ file: File | null }>({ file: null });

function openReplace(): void {
    replaceForm.reset();
    replaceForm.clearErrors();
    replacing.value = true;
}

function onPick(event: Event): void {
    replaceForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function saveReplace(): void {
    replaceForm.post(route('documents.replace', { document: props.document.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (replacing.value = false),
    });
}

// --- Delete ---------------------------------------------------------------------------

const deleting = ref(false);
const deleteBusy = ref(false);

function confirmDelete(): void {
    router.delete(route('documents.destroy', { document: props.document.id }), {
        preserveScroll: true,
        onStart: () => (deleteBusy.value = true),
        onFinish: () => {
            deleteBusy.value = false;
            deleting.value = false;
        },
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :aria-label="trans('documents.manage.menu', { name })">
                <PhDotsThreeVertical class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem class="gap-2" @select="openEdit">
                <PhPencilSimple class="size-4" />
                {{ trans('documents.manage.edit') }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="isFile" class="gap-2" @select="openReplace">
                <PhArrowsClockwise class="size-4" />
                {{ trans('documents.manage.replace') }}
            </DropdownMenuItem>
            <DropdownMenuItem class="text-destructive gap-2" @select="deleting = true">
                <PhTrash class="size-4" />
                {{ trans('documents.manage.delete') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="editing">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('documents.manage.edit_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveEdit">
                <div class="grid gap-2">
                    <Label :for="`document-${document.id}-title`">{{ trans('documents.manage.field.title') }}</Label>
                    <Input :id="`document-${document.id}-title`" v-model="editForm.title" :placeholder="document.filename ?? ''" />
                    <p v-if="editForm.errors.title" role="alert" class="text-destructive text-sm">{{ editForm.errors.title }}</p>
                </div>
                <div class="grid gap-2">
                    <Label :for="`document-${document.id}-description`">{{ trans('documents.manage.field.description') }}</Label>
                    <Textarea :id="`document-${document.id}-description`" v-model="editForm.description" :rows="4" />
                    <p v-if="editForm.errors.description" role="alert" class="text-destructive text-sm">{{ editForm.errors.description }}</p>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="editForm.processing">{{ trans('documents.manage.save') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="editForm.processing" @click="editing = false">
                        {{ trans('documents.manage.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="replacing">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('documents.manage.replace_title', { name }) }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveReplace">
                <div class="grid gap-2">
                    <Label :for="`document-${document.id}-file`">{{ trans('documents.manage.field.file') }}</Label>
                    <Input :id="`document-${document.id}-file`" type="file" required @change="onPick" />
                    <Progress v-if="replaceForm.progress" :model-value="replaceForm.progress.percentage ?? 0" class="h-1.5" />
                    <p v-if="replaceForm.errors.file" role="alert" class="text-destructive text-sm">{{ replaceForm.errors.file }}</p>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="replaceForm.processing || !replaceForm.file">
                        {{ trans('documents.manage.replace_save') }}
                    </Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="replaceForm.processing" @click="replacing = false">
                        {{ trans('documents.manage.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleting">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('documents.manage.delete_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('documents.manage.delete_body', { name }) }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="deleteBusy">{{ trans('documents.manage.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/80"
                    :disabled="deleteBusy"
                    @click.prevent="confirmDelete"
                >
                    {{ trans('documents.manage.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
