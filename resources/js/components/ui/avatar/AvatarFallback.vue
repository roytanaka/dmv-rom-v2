<script setup lang="ts">
import { cn } from '@/lib/utils';
import { AvatarFallback, type AvatarFallbackProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<AvatarFallbackProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = computed(() => {
    const { class: _class, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <!-- Fills the Avatar root so a fallback's own bg/shape (e.g. the dark-bar
         disc) covers the whole avatar instead of shrink-wrapping the initials
         into an ellipse. The root owns bg/shape + overflow-hidden. -->
    <AvatarFallback v-bind="delegatedProps" :class="cn('flex size-full items-center justify-center', props.class)">
        <slot />
    </AvatarFallback>
</template>
