<script setup lang="ts">
// Create a Folder in the open Folder (or at the top level), or rename one (#714, ADR-0030 §3).
// Lean form: a name field, plus who can read it for a top-level Folder (#715, §5); a
// subfolder inherits its top-level Folder's setting and sends none. Given `categories`, it
// also files the Folder under one of them (#724, ADR-0030 §4). The server keeps names unique
// among siblings, holds the depth limit and refuses another Folder's Document category.
import DocumentCategorySelect from '@/components/DocumentCategorySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type FolderVisibility, type LibraryCategory } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    /** Edit this Folder; when absent, create one under `parentId` in the Group. */
    folder?: { id: number; name: string; visibility: FolderVisibility; categoryId: number | null };
    groupSlug?: string;
    parentId?: number | null;
    /** The Folder is (or will be) top-level, so it sets who can read it. */
    topLevel: boolean;
    /** The Document categories of the Folder it sits in; given, the dialog shows the Category select (#724). */
    categories?: LibraryCategory[];
}>();
const open = defineModel<boolean>('open', { required: true });

const VISIBILITIES: FolderVisibility[] = ['group', 'members'];

type FolderForm = { name: string; visibility: FolderVisibility; category_id: number | null };

const form = useForm<FolderForm>({ name: '', visibility: 'group', category_id: null });
// A create can also be refused on `parent_id` (the depth limit, a vanished parent).
const error = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors.name ?? errors.parent_id;
});
const fieldId = computed(() => (props.folder ? `folder-${props.folder.id}` : 'new-folder'));

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.name = props.folder?.name ?? '';
    form.visibility = props.folder?.visibility ?? 'group';
    form.category_id = props.folder?.categoryId ?? null;
    form.clearErrors();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    // Only a top-level Folder carries a visibility; the server refuses one on a subfolder.
    // The Document category rides only when the dialog shows its select.
    const payload = (data: FolderForm) => ({
        name: data.name,
        ...(props.topLevel ? { visibility: data.visibility } : {}),
        ...(props.categories ? { category_id: data.category_id } : {}),
    });

    if (props.folder) {
        form.transform(payload).patch(route('document-folders.update', { folder: props.folder.id }), options);
    } else {
        form.transform((data) => ({ ...payload(data), parent_id: props.parentId ?? null })).post(
            route('document-folders.store', { group: props.groupSlug }),
            options,
        );
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans(folder ? 'document_folders.edit_title' : 'document_folders.create_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label :for="`${fieldId}-name`">{{ trans('document_folders.field.name') }}</Label>
                    <Input :id="`${fieldId}-name`" v-model="form.name" required maxlength="255" />
                    <InputError :message="error" />
                </div>
                <div v-if="topLevel" class="grid gap-2">
                    <Label :for="`${fieldId}-visibility`">{{ trans('document_folders.field.visibility') }}</Label>
                    <Select v-model="form.visibility">
                        <SelectTrigger :id="`${fieldId}-visibility`" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="value in VISIBILITIES" :key="value" :value="value">
                                {{ trans(`document_folders.visibility.${value}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.visibility" />
                </div>
                <DocumentCategorySelect
                    v-if="categories"
                    :id="`${fieldId}-category`"
                    v-model="form.category_id"
                    :categories="categories"
                    :error="form.errors.category_id"
                />
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('document_folders.save') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('document_folders.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
