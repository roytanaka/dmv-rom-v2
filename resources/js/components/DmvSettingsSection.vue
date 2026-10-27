<script setup lang="ts">
// One section of a DMV Settings form (#848): a Card with an id the section index links to, a
// heading, and phone-tight padding. The scroll margin keeps an anchor jump clear of the sticky
// top bar. The body is a single column of fields with the house gap unless `layout` says
// otherwise (the flag grids pass their own).
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

withDefaults(defineProps<{ id: string; title: string; layout?: string }>(), { layout: 'flex flex-col gap-4' });
</script>

<template>
    <Card :id="id" class="scroll-mt-[calc(var(--header-offset)+1rem)]">
        <CardHeader class="p-4 sm:p-6">
            <CardTitle class="text-lg">{{ title }}</CardTitle>
        </CardHeader>
        <CardContent :class="`p-4 pt-0 sm:p-6 sm:pt-0 ${layout}`">
            <slot />
        </CardContent>
    </Card>
</template>
