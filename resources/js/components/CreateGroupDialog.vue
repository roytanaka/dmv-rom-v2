<script setup lang="ts">
// Create a Group from DMV Settings (#842, ADR-0036 §4). Lean form: name, slug, parent, kind;
// every other setting starts at its default on the server. The slug follows the name until the
// slug field is edited; it is set here only. The parent is any active Group, the root included.
// On success the server redirects to the new Group's structure form.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

export interface ParentOption {
    id: number;
    name: string;
    depth: number;
}

defineProps<{ parentOptions: ParentOption[] }>();
const open = defineModel<boolean>('open', { required: true });

// The six Kind cases (App\Enums\Kind), labelled from dmv_settings.kind.*.
const KINDS = ['standing_committee', 'program', 'working_group', 'project', 'cohort', 'container'] as const;

const form = useForm<{ name: string; slug: string; parent_id: number | null; kind: string | null }>({
    name: '',
    slug: '',
    parent_id: null,
    kind: null,
});

// Proposed from the name while the slug field is untouched: lowercase, accents dropped,
// every run of other characters one hyphen, none at either end.
const slugTouched = ref(false);
const slugify = (name: string): string =>
    name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) form.slug = slugify(name);
    },
);

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    slugTouched.value = false;
});

function submit(): void {
    form.post(route('dmv-settings.groups.store'));
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('dmv_settings.create.title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="new-group-name">{{ trans('dmv_settings.field.name') }}</Label>
                    <Input id="new-group-name" v-model="form.name" required maxlength="255" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-group-slug">{{ trans('dmv_settings.field.slug') }}</Label>
                    <Input id="new-group-slug" v-model="form.slug" required maxlength="255" @input="slugTouched = true" />
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-group-parent">{{ trans('dmv_settings.field.parent') }}</Label>
                    <NativeSelect id="new-group-parent" v-model="form.parent_id" required>
                        <option :value="null" disabled>{{ trans('dmv_settings.choose_parent') }}</option>
                        <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                            {{ '— '.repeat(option.depth) + option.name }}
                        </option>
                    </NativeSelect>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-group-kind">{{ trans('dmv_settings.field.kind') }}</Label>
                    <NativeSelect id="new-group-kind" v-model="form.kind" required>
                        <option :value="null" disabled>{{ trans('dmv_settings.choose_kind') }}</option>
                        <option v-for="kind in KINDS" :key="kind" :value="kind">{{ trans(`dmv_settings.kind.${kind}`) }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.kind" />
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">{{ trans('dmv_settings.create.submit') }}</Button>
                    <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                        {{ trans('dmv_settings.create.cancel') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
