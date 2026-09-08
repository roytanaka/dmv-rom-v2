<script setup lang="ts">
// The radius ramp is kept from the starter kit intentionally (ADR / PRD #37):
// the square identity is applied at the component level in PR #2, so this
// proportional scale stays as a working escape hatch for deliberate rounding.
// Each step maps to a `rounded-*` utility via the `--radius-*` theme tokens.
const radiusSteps = [
    { name: 'sm', class: 'rounded-sm' },
    { name: 'md', class: 'rounded-md' },
    { name: 'lg', class: 'rounded-lg' },
    { name: 'xl', class: 'rounded-xl' },
] as const;
</script>

<template>
    <section id="radius" aria-labelledby="radius-heading" class="mb-12 scroll-mt-24">
        <h2 id="radius-heading" class="text-xl font-semibold tracking-tight">Radius</h2>
        <p class="text-muted-foreground mt-1 text-sm">
            The proportional <code>sm/md/lg/xl</code> ramp from the <code>--radius</code> scale, kept intentionally.
        </p>

        <ul class="mt-6 flex flex-wrap gap-6">
            <li v-for="step in radiusSteps" :key="step.name" class="flex flex-col items-center gap-2">
                <div :class="['bg-muted border-border size-20 border', step.class]" />
                <span class="text-muted-foreground text-sm">{{ step.name }}</span>
            </li>
        </ul>
    </section>
</template>
