<script setup lang="ts">
import ClipboardCopy from '@/components/ClipboardCopy.vue';

// A minimal, dependency-free code block for the /design-system gallery: a
// square <pre> on the muted surface with a copy affordance in the corner.
// The copy behaviour and a11y live in ClipboardCopy (no syntax-highlighting
// library, per the slice's no-new-dependency rule). Snippets are illustrative,
// not an API contract — the page says so up top.
const props = defineProps<{
    value: string;
    label?: string;
}>();
</script>

<template>
    <div class="border-border bg-muted/40 relative mt-4 border">
        <pre class="text-foreground/80 overflow-x-auto px-3 py-2.5 font-mono text-xs leading-relaxed"><code>{{ props.value }}</code></pre>
        <ClipboardCopy
            :value="value"
            :label="label ?? 'Copy code snippet'"
            icon-class="size-4"
            class="group/snippet bg-background/80 text-muted-foreground/60 hover:text-foreground border-border absolute top-1.5 right-1.5 inline-flex size-7 cursor-pointer items-center justify-center border transition-colors"
        />
    </div>
</template>
