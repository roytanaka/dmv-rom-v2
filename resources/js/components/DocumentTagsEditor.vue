<script setup lang="ts">
// Set which Tags one Document carries (#717, spec #290, ADR-0030 §4): a dialog of the Group's
// Tags as checkboxes; saving sends the whole set. Rendered for managers only; the
// DocumentTagPolicy enforces the write.
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { type LibraryTag } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { PhTag } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{ documentId: number; name: string; tags: LibraryTag[]; selected: LibraryTag[] }>();

const open = ref(false);
const form = useForm<{ tags: number[] }>({ tags: [] });

function start(): void {
    form.tags = props.selected.map((tag) => tag.id);
    form.clearErrors();
    open.value = true;
}

function toggle(id: number, checked: boolean): void {
    form.tags = checked ? [...form.tags, id] : form.tags.filter((tag) => tag !== id);
}

function save(): void {
    form.put(route('documents.tags.update', { document: props.documentId }), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Button type="button" variant="ghost" size="sm" :aria-label="trans('document_tags.edit', { name })" @click="start">
        <PhTag class="size-4" />
    </Button>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('document_tags.edit_title', { name }) }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <p v-if="tags.length === 0" class="text-muted-foreground text-sm">{{ trans('document_tags.none') }}</p>
                <div v-for="tag in tags" :key="tag.id" class="flex items-center gap-3">
                    <Checkbox
                        :id="`document-${documentId}-tag-${tag.id}`"
                        :checked="form.tags.includes(tag.id)"
                        @update:checked="(checked: boolean) => toggle(tag.id, checked)"
                    />
                    <Label :for="`document-${documentId}-tag-${tag.id}`">{{ tag.name }}</Label>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('document_tags.save') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('document_tags.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
