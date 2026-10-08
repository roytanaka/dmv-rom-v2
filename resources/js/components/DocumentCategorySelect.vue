<script setup lang="ts">
// The Category field of a Document library dialog (#724, spec #721, ADR-0030 §4): label,
// select, error (Lean forms). The choices are one Folder's Document categories plus "No
// category" (Other). The model is the Document category id, or null for none; the dialog sends
// it as `category_id`, and the server refuses another Folder's Document category.
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type LibraryCategory } from '@/types';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    /** The field's id, unique on the page. */
    id: string;
    /** The Folder's Document categories, sorted by name. */
    categories: LibraryCategory[];
    error?: string;
}>();
const model = defineModel<number | null>({ required: true });

// The select holds strings; "No category" is a sentinel, since an item's value cannot be empty.
const NONE = 'none';
const value = computed({
    get: () => (model.value === null ? NONE : String(model.value)),
    set: (picked: string) => (model.value = picked === NONE ? null : Number(picked)),
});
// A Document category no longer in the list (deleted meanwhile) reads as "No category".
const known = computed(() => model.value === null || props.categories.some((category) => category.id === model.value));
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ trans('document_categories.field.category') }}</Label>
        <Select :model-value="known ? value : NONE" @update:model-value="(picked) => (value = String(picked))">
            <SelectTrigger :id="id" class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="NONE">{{ trans('document_categories.none') }}</SelectItem>
                <SelectItem v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</SelectItem>
            </SelectContent>
        </Select>
        <InputError :message="error" />
    </div>
</template>
