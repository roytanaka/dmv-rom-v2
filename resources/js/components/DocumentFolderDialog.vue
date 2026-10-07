<script setup lang="ts">
// Create a Folder in the open Folder (or at the top level), or rename one (#714, ADR-0030 §3).
// Lean form: a name field, plus who can read it for a top-level Folder (#715, §5); a
// subfolder inherits its top-level Folder's setting and sends none. The server keeps names
// unique among siblings and holds the depth limit.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type FolderVisibility } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    /** Edit this Folder; when absent, create one under `parentId` in the Group. */
    folder?: { id: number; name: string; visibility: FolderVisibility };
    groupSlug?: string;
    parentId?: number | null;
    /** The Folder is (or will be) top-level, so it sets who can read it. */
    topLevel: boolean;
}>();
const open = defineModel<boolean>('open', { required: true });

const VISIBILITIES: FolderVisibility[] = ['group', 'members'];

type FolderForm = { name: string; visibility: FolderVisibility };

const form = useForm<FolderForm>({ name: '', visibility: 'group' });
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
    form.clearErrors();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    // Only a top-level Folder carries a visibility; the server refuses one on a subfolder.
    const payload = (data: FolderForm) => (props.topLevel ? { name: data.name, visibility: data.visibility } : { name: data.name });

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
                <DialogTitle>{{
                    trans(folder ? (topLevel ? 'document_folders.edit_title' : 'document_folders.rename_title') : 'document_folders.create_title')
                }}</DialogTitle>
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
