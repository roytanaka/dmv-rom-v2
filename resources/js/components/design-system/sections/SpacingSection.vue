<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';

// Spacing scale. The bar width comes from the live `w-*` utility (4px base step),
// so the specimen renders the real spacing rather than asserting px in a table.
// `px` is the resolved value at the 16px root, shown only as a label.
const spacingSteps = [
    { class: 'w-0.5', px: 2 },
    { class: 'w-1', px: 4 },
    { class: 'w-2', px: 8 },
    { class: 'w-3', px: 12 },
    { class: 'w-4', px: 16 },
    { class: 'w-6', px: 24 },
    { class: 'w-8', px: 32 },
    { class: 'w-10', px: 40 },
    { class: 'w-12', px: 48 },
    { class: 'w-16', px: 64 },
    { class: 'w-20', px: 80 },
    { class: 'w-24', px: 96 },
] as const;
</script>

<template>
    <section id="spacing" aria-labelledby="spacing-heading" class="mb-12 scroll-mt-24">
        <h2 id="spacing-heading" class="text-xl font-semibold tracking-tight">Spacing</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The 4px base step (<code>0.25rem</code>) and the utilities built on it. Each bar is sized by the live
            <code>w-*</code> utility, so the scale is correct by construction — the px label is the resolved value at the 16px root.
        </p>

        <ul class="mt-6 space-y-2">
            <li v-for="step in spacingSteps" :key="step.class" class="flex items-center gap-4">
                <CopyButton :value="step.class" class="w-16 flex-none"
                    ><code class="text-muted-foreground font-mono text-xs">{{ step.class }}</code></CopyButton
                >
                <div :class="['bg-rom-slate h-4 flex-none', step.class]" />
                <span class="text-muted-foreground font-mono text-xs">{{ step.px }}px</span>
            </li>
        </ul>
    </section>
</template>
