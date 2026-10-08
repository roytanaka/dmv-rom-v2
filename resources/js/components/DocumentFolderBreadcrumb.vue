<script setup lang="ts">
// The open Folder's breadcrumb on the Group Documents tab (#714, ADR-0030 §3): the library root,
// the Folders above, then the open Folder. At the root (#726) it is one current item, Documents,
// so the root reads like every Folder page. Folder names are content, shown as written.
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { type LibraryFolder } from '@/types';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ groupSlug: string; folder: LibraryFolder | null; ancestors: LibraryFolder[] }>();

const localizeHref = useLocalizedHref();
const rootHref = computed(() => localizeHref(route('groups.show', { group: props.groupSlug, section: 'documents' }, false)));
</script>

<template>
    <Breadcrumb :aria-label="trans('document_folders.breadcrumb')">
        <BreadcrumbList>
            <BreadcrumbItem>
                <BreadcrumbPage v-if="folder === null">{{ trans('document_folders.root') }}</BreadcrumbPage>
                <BreadcrumbLink v-else as-child>
                    <Link :href="rootHref">{{ trans('document_folders.root') }}</Link>
                </BreadcrumbLink>
            </BreadcrumbItem>
            <template v-for="ancestor in ancestors" :key="ancestor.id">
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbLink as-child>
                        <Link :href="ancestor.href">{{ ancestor.name }}</Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>
            </template>
            <template v-if="folder">
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbPage>{{ folder.name }}</BreadcrumbPage>
                </BreadcrumbItem>
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
