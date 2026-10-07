<script setup lang="ts">
// Add or edit a link Document (#716, ADR-0030 §7): a title and a web address. One dialog for
// both: `document` null adds a link at the library root, a link row edits that link. The
// server allows http and https only; the Form Request re-checks who may write.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type LibraryDocument } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { watch } from 'vue';

const props = defineProps<{ groupSlug: string; document: LibraryDocument | null }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ title: '', url: '' });

// Fill the form each time the dialog opens: blank to add, the link's values to edit.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.title = props.document?.title ?? '';
    form.url = props.document?.url ?? '';
    form.clearErrors();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.document === null) {
        form.post(route('documents.links.store', { group: props.groupSlug }), options);
    } else {
        form.patch(route('documents.links.update', { document: props.document.id }), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans(document === null ? 'documents.link.add_title' : 'documents.link.edit_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="link-document-title">{{ trans('documents.link.field.title') }}</Label>
                    <Input id="link-document-title" v-model="form.title" required maxlength="255" />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="link-document-url">{{ trans('documents.link.field.url') }}</Label>
                    <Input id="link-document-url" v-model="form.url" type="url" required maxlength="2048" />
                    <InputError :message="form.errors.url" />
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('documents.link.save') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('documents.link.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
