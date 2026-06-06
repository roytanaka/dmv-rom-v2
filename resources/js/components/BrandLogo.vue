<script setup lang="ts">
// The ROM/DMV brand wordmark. Always the SVG asset — never live text — so the
// lockup is never reflowed or restyled by the type scale. White on dark surfaces
// (the charcoal rail, the black top bar in #67), black on light.
import logoBlack from '../../images/brand/romdmv-logo-black.svg';
import logoWhite from '../../images/brand/romdmv-logo-white.svg';
import { computed, type HTMLAttributes } from 'vue';

interface Props {
    variant?: 'white' | 'black';
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'white',
});

const src = computed(() => (props.variant === 'white' ? logoWhite : logoBlack));
</script>

<template>
    <img :src="src" alt="ROM DMV — Department of Museum Volunteers" :class="props.class" />
</template>
