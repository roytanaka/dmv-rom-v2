<script setup lang="ts">
// The Librarian's Tag list (#717, spec #290, ADR-0030 §4): create, rename and delete the
// Group's Tags in one dialog. Deleting a Tag removes it from every Document (the server
// cascades). Rendered for managers only; the DocumentTagPolicy enforces every write.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type LibraryTag } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { PhPencilSimple, PhTag, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{ tags: LibraryTag[]; groupSlug: string }>();

const createForm = useForm({ name: '' });

function create(): void {
    createForm.post(route('document-tags.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

// The Tag being renamed, or null.
const editing = ref<number | null>(null);
const renameForm = useForm({ name: '' });

function startRename(tag: LibraryTag): void {
    renameForm.name = tag.name;
    renameForm.clearErrors();
    editing.value = tag.id;
}

function rename(tag: LibraryTag): void {
    renameForm.patch(route('document-tags.update', { documentTag: tag.id }), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function destroy(tag: LibraryTag): void {
    if (window.confirm(trans('document_tags.confirm_delete'))) {
        router.delete(route('document-tags.destroy', { documentTag: tag.id }), { preserveScroll: true });
    }
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button type="button" variant="outline" size="sm" class="gap-1.5">
                <PhTag class="size-4" aria-hidden="true" />
                {{ trans('document_tags.manage') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('document_tags.manage_title') }}</DialogTitle>
            </DialogHeader>

            <p v-if="tags.length === 0" class="text-muted-foreground text-sm">{{ trans('document_tags.none') }}</p>
            <ul v-else class="flex flex-col gap-1">
                <li v-for="tag in tags" :key="tag.id">
                    <form v-if="editing === tag.id" class="flex flex-col gap-1" @submit.prevent="rename(tag)">
                        <div class="flex gap-2">
                            <Input
                                v-model="renameForm.name"
                                :aria-label="trans('document_tags.rename', { name: tag.name })"
                                required
                                maxlength="100"
                            />
                            <Button type="submit" size="sm" :disabled="renameForm.processing">{{ trans('document_tags.save') }}</Button>
                            <Button type="button" variant="ghost" size="sm" @click="editing = null">{{ trans('document_tags.cancel') }}</Button>
                        </div>
                        <InputError :message="renameForm.errors.name" />
                    </form>
                    <div v-else class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm">{{ tag.name }}</span>
                        <div class="flex shrink-0">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="trans('document_tags.rename', { name: tag.name })"
                                @click="startRename(tag)"
                            >
                                <PhPencilSimple class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="trans('document_tags.delete', { name: tag.name })"
                                @click="destroy(tag)"
                            >
                                <PhTrash class="size-4" />
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>

            <form class="grid gap-2" @submit.prevent="create">
                <Label for="document-tag-new">{{ trans('document_tags.new') }}</Label>
                <div class="flex gap-2">
                    <Input id="document-tag-new" v-model="createForm.name" required maxlength="100" />
                    <Button type="submit" size="sm" :disabled="createForm.processing">{{ trans('document_tags.add') }}</Button>
                </div>
                <InputError :message="createForm.errors.name" />
            </form>
        </DialogContent>
    </Dialog>
</template>
