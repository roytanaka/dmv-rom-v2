<script setup lang="ts">
// ROM identity: hairline neutral row separators (no zebra), a quiet whole-row
// neutral hover fill, and the selected row taking the heritage-blue wash
// (`data-[state=selected]:bg-rom-slate-50`).
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tr :class="cn('border-b transition-colors hover:bg-muted data-[state=selected]:bg-rom-slate-50', props.class)">
        <slot />
    </tr>
</template>
