<script setup lang="ts">
// ROM identity: uppercase bold black column labels at the 13px-class size
// (`text-xs` — the sanctioned passive-label exception to the 15px floor, same
// category as .eyebrow), replacing upstream's medium muted-gray heads.
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <th
        :class="
            cn('h-12 px-4 text-left align-middle text-xs font-bold tracking-wide text-primary uppercase [&:has([role=checkbox])]:pr-0', props.class)
        "
    >
        <slot />
    </th>
</template>
