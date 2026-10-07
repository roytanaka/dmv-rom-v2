<script setup lang="ts">
// Move a Folder or a Document to another Folder of the library, or to its top level (#714,
// ADR-0030 §3). One select lists every Folder as its path. For a Folder, the picker leaves
// out the Folder itself, its descendants, and any target that would push its subtree past the
// depth limit; the move Form Requests re-check all of it. Lean form: label, select, error.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type FolderDestination } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    /** What moves: a Folder (with its subtree) or a Document. */
    kind: 'folder' | 'document';
    id: number;
    name: string;
    /** Where it sits now: the parent Folder, or null at the top level. */
    currentFolderId: number | null;
    destinations: FolderDestination[];
    maxDepth: number;
}>();
const open = defineModel<boolean>('open', { required: true });

const TOP = 'top';

const form = useForm<{ target: string }>({ target: TOP });
form.transform(({ target }) => {
    const value = target === TOP ? null : Number(target);
    return props.kind === 'folder' ? { parent_id: value } : { folder_id: value };
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.target = props.currentFolderId === null ? TOP : String(props.currentFolderId);
    form.clearErrors();
});

// The Folder's own subtree: its id and every descendant's, and how many levels it spans.
const subtree = computed(() => {
    const ids = new Set<number>([props.id]);
    let height = 1;
    let frontier = [props.id];
    while (frontier.length) {
        frontier = props.destinations.filter((item) => item.parentId !== null && frontier.includes(item.parentId)).map((item) => item.id);
        frontier.forEach((id) => ids.add(id));
        if (frontier.length) height++;
    }
    return { ids, height };
});

const options = computed(() =>
    props.kind === 'document'
        ? props.destinations
        : props.destinations.filter((item) => !subtree.value.ids.has(item.id) && item.depth + subtree.value.height <= props.maxDepth),
);

// The server names the field it checked: `parent_id` for a Folder, `folder_id` for a Document.
const error = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors.parent_id ?? errors.folder_id;
});

function submit(): void {
    const url = props.kind === 'folder' ? route('document-folders.move', { folder: props.id }) : route('documents.move', { document: props.id });
    form.patch(url, { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('document_folders.move_title', { name }) }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label :for="`move-${kind}-${id}`">{{ trans('document_folders.field.destination') }}</Label>
                    <Select v-model="form.target">
                        <SelectTrigger :id="`move-${kind}-${id}`" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="TOP">{{ trans('document_folders.top_level') }}</SelectItem>
                            <SelectItem v-for="item in options" :key="item.id" :value="String(item.id)">{{ item.path.join(' / ') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="error" />
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('document_folders.move') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('document_folders.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
