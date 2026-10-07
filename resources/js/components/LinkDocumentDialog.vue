<script setup lang="ts">
// Add a link Document (#716, ADR-0030 §7): a title and a web address, at the library root.
// Editing a link lives in DocumentActions (#713). The server allows http and https only; the
// Form Request re-checks who may write.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { watch } from 'vue';

// `folderId`: the open Folder the link lands in (#714); null at the library root.
const props = defineProps<{ groupSlug: string; folderId?: number | null }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ title: '', url: '' });

// Start blank each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
});

function submit(): void {
    form.transform((data) => ({ ...data, folder_id: props.folderId ?? null })).post(route('documents.links.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('documents.link.add_title') }}</DialogTitle>
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
