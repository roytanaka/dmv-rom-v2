<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';

// Elevation ramp. Each tile applies the live `shadow-*` utility so the scale
// stays correct by construction rather than being a hand-maintained table. Roles
// reflect actual usage across the customised components (card/button → xs, etc.).
// Full class strings are written as literals so Tailwind's scanner keeps them.
const shadowSteps = [
    { class: 'shadow-xs', role: 'cards & buttons — resting' },
    { class: 'shadow-sm', role: 'sidebar, raised buttons' },
    { class: 'shadow-md', role: 'dropdowns, tooltips' },
    { class: 'shadow-lg', role: 'dialogs, sheets, menus' },
] as const;
</script>

<template>
    <section id="elevation" aria-labelledby="elevation-heading" class="mb-12 scroll-mt-24">
        <h2 id="elevation-heading" class="text-xl font-semibold tracking-tight">Elevation</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The shadow ramp the components lift on, rendered live from the <code>shadow-*</code> utilities — the calm container stays at
            <code>shadow-xs</code>; popovers and dialogs lift progressively. Shadows are deliberately soft on the white canvas.
        </p>

        <ul class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
            <li v-for="step in shadowSteps" :key="step.class" class="flex flex-col items-center gap-3">
                <div :class="['bg-card border-border size-20 border', step.class]" />
                <div class="text-center">
                    <CopyButton :value="step.class"
                        ><code class="text-foreground text-sm font-medium">{{ step.class }}</code></CopyButton
                    >
                    <p class="text-muted-foreground mt-1 text-xs leading-snug">{{ step.role }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
