<script setup lang="ts">
// The open Folder's header on the Group Documents tab (#726, spec #721, ADR-0030 §4): an
// open-folder icon, the Folder name and what it holds ("7 categories · 13 folders", "4 files"),
// then who can read it (#715). The counts cover the whole Folder, not a filtered section, so the
// header stays put while the Category filter changes. The name is content, shown as written.
import { type LibraryFolder } from '@/types';
import { PhFolderOpen } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ folder: LibraryFolder; categoryCount: number; folderCount: number; documentCount: number }>();

// Each part only when there is some; files also when the Folder holds nothing, so an empty
// Folder reads "0 files".
const holds = computed(() =>
    [
        props.categoryCount > 0
            ? transChoice('document_folders.count_categories', props.categoryCount, { count: String(props.categoryCount) })
            : null,
        props.folderCount > 0 ? transChoice('document_categories.count.folders', props.folderCount, { count: String(props.folderCount) }) : null,
        props.documentCount > 0 || props.categoryCount + props.folderCount === 0
            ? transChoice('document_categories.count.files', props.documentCount, { count: String(props.documentCount) })
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <div class="flex items-start gap-3">
        <PhFolderOpen class="text-muted-foreground mt-0.5 h-7 w-7 shrink-0" aria-hidden="true" />
        <div class="flex min-w-0 flex-col gap-0.5">
            <h2 class="text-rom-ink text-xl font-semibold break-words">{{ folder.name }}</h2>
            <p class="text-muted-foreground text-sm">{{ holds }}</p>
            <p class="text-muted-foreground text-sm">
                {{ trans('document_folders.readable_by', { who: trans(`document_folders.visibility.${folder.visibility}`) }) }}
            </p>
        </div>
    </div>
</template>
