<script setup lang="ts">
// ROM identity: the header row sits on a 1px black underline (`border-primary`
// overrides the neutral hairline TableRow draws) — no muted-gray header band.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <thead :class="cn('[&_tr]:border-b [&_tr]:border-primary', props.class)">
        <slot />
    </thead>
</template>
