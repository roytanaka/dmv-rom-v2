<script setup lang="ts">
// The breadcrumb strip (Chrome) — a slim wayfinding band between the black top bar
// and the white content canvas, on a faint slate wash so it reads as its own strip.
// Fed by each page's `breadcrumbs` prop; the restyled shadcn breadcrumb primitive
// (heritage-blue link hover, ink current page) renders the trail. Titles come from
// the page, so they are translatable at the source.
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header v-if="breadcrumbs.length > 0" class="bg-rom-slate-50 border-border flex h-12 shrink-0 items-center border-b px-6 md:px-4">
        <Breadcrumb>
            <BreadcrumbList>
                <template v-for="(item, index) in breadcrumbs" :key="index">
                    <BreadcrumbItem>
                        <template v-if="index === breadcrumbs.length - 1">
                            <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                        </template>
                        <template v-else>
                            <BreadcrumbLink :href="item.href">
                                {{ item.title }}
                            </BreadcrumbLink>
                        </template>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                </template>
            </BreadcrumbList>
        </Breadcrumb>
    </header>
</template>
