<script setup lang="ts">
// Create a Folder in the open Folder (or at the top level), or rename one (#714, ADR-0030 §3).
// Lean form: one name field. The server keeps names unique among siblings and holds the
// depth limit.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    /** Rename this Folder; when absent, create one under `parentId` in the Group. */
    folder?: { id: number; name: string };
    groupSlug?: string;
    parentId?: number | null;
}>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ name: '' });
// A create can also be refused on `parent_id` (the depth limit, a vanished parent).
const error = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors.name ?? errors.parent_id;
});
const fieldId = computed(() => (props.folder ? `folder-${props.folder.id}-name` : 'new-folder-name'));

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.name = props.folder?.name ?? '';
    form.clearErrors();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.folder) {
        form.patch(route('document-folders.update', { folder: props.folder.id }), options);
    } else {
        form.transform((data) => ({ ...data, parent_id: props.parentId ?? null })).post(
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
                <DialogTitle>{{ trans(folder ? 'document_folders.rename_title' : 'document_folders.create_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label :for="fieldId">{{ trans('document_folders.field.name') }}</Label>
                    <Input :id="fieldId" v-model="form.name" required maxlength="255" />
                    <InputError :message="error" />
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
