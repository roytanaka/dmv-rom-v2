<script setup lang="ts">
// The Document library's one-Tag filter (#717, spec #290, ADR-0030 §4). Picking a Tag reloads
// the Documents tab with `?tag=` so the server lists every readable Document with that Tag
// across all Folders; "All documents" drops the filter. Stays on the current (localized) path.
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type LibraryTag, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ tags: LibraryTag[]; tag: LibraryTag | null }>();

const page = usePage<SharedData>();

const ALL = 'all';

const selected = computed({
    get: () => (props.tag === null ? ALL : String(props.tag.id)),
    set: (value: string) => {
        router.get(page.url.split('?')[0], value === ALL ? {} : { tag: value }, { preserveScroll: true, preserveState: true });
    },
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Label for="document-tag-filter" class="shrink-0">{{ trans('document_tags.filter') }}</Label>
        <Select v-model="selected">
            <SelectTrigger id="document-tag-filter" class="w-full sm:w-56">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL">{{ trans('document_tags.all') }}</SelectItem>
                <SelectItem v-for="item in tags" :key="item.id" :value="String(item.id)">{{ item.name }}</SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
