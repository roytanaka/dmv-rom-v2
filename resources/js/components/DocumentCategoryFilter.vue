<script setup lang="ts">
// The Document library's Category filter (#725, spec #721, ADR-0030 §4). Lists "All
// categories" plus the open Folder's Document categories; picking one reloads the page with
// `?category=` so only that section shows, and "All categories" drops it. Greyed out when the
// Folder has no Document categories and no filter is set. Stays on the current (localized) path;
// a sub folder's link carries no query, so opening one clears the filter.
//
// `?category=` may name none of this Folder's Document categories (another Folder's, one hidden
// from the viewer, or a bogus id); the page then shows nothing, and the trigger says the chosen
// Document category is unavailable rather than "All categories".
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type LibraryCategory, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ categories: LibraryCategory[]; category: number | null }>();

const page = usePage<SharedData>();

const ALL = 'all';

const selected = computed({
    // A filter naming none of this Folder's Document categories selects nothing; the
    // placeholder says so.
    get: () => (props.categories.some((item) => item.id === props.category) ? String(props.category) : props.category === null ? ALL : ''),
    set: (value: string) => {
        router.get(page.url.split('?')[0], value === ALL ? {} : { category: value }, { preserveScroll: true, preserveState: true });
    },
});
const unavailable = computed(() => props.category !== null && selected.value === '');
</script>

<template>
    <Select v-model="selected" :disabled="categories.length === 0 && !unavailable">
        <SelectTrigger class="w-full sm:w-56" :aria-label="trans('document_categories.filter.label')">
            <SelectValue :placeholder="trans(unavailable ? 'document_categories.filter.unavailable' : 'document_categories.filter.all')" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="ALL">{{ trans('document_categories.filter.all') }}</SelectItem>
            <SelectItem v-for="item in categories" :key="item.id" :value="String(item.id)">{{ item.name }}</SelectItem>
        </SelectContent>
    </Select>
</template>
