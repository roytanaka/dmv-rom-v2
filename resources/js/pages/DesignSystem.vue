<script setup lang="ts">
import DesignSystemLayout from '@/layouts/DesignSystemLayout.vue';
import { Head } from '@inertiajs/vue3';

// Colour-token specimens. Each swatch fills its chip from the live CSS variable
// (`var(--token)`) so the page renders the actual token value — the palette is
// verified here by eye, not asserted in tests (PRD #37 / #41). Hex labels mirror
// the values in resources/css/app.css; `note` flags a token that resolves
// through another (e.g. --info → --rom-slate) or its role.
type Swatch = { token: string; hex: string; note?: string };
type SwatchGroup = { id: string; title: string; desc: string; swatches: Swatch[] };

const colorGroups: SwatchGroup[] = [
    {
        id: 'brand',
        title: 'Brand',
        desc: 'ROM black & white with one muted heritage-blue accent (after rom.on.ca). Black for actions, heritage blue for wayfinding — neither is decorative.',
        swatches: [
            { token: '--rom-ink', hex: '#000000', note: 'Black — text, logo, top bar, buttons' },
            { token: '--background', hex: '#ffffff', note: 'White — the canvas does the work' },
            { token: '--rom-gray', hex: '#f5f5f5', note: 'Support gray — quiet fills, zebra rows' },
            { token: '--rom-slate', hex: '#516d80', note: 'Heritage blue — the single accent' },
        ],
    },
    {
        id: 'neutral',
        title: 'Neutral',
        desc: 'A clean greyscale on a pure-white canvas, anchored on ROM’s #8b8b85 support gray. Never tinted by the accent.',
        swatches: [
            { token: '--background', hex: '#ffffff' },
            { token: '--foreground', hex: '#1a1a1a' },
            { token: '--card', hex: '#ffffff' },
            { token: '--card-foreground', hex: '#1a1a1a' },
            { token: '--popover', hex: '#ffffff' },
            { token: '--popover-foreground', hex: '#1a1a1a' },
            { token: '--primary', hex: '#000000' },
            { token: '--primary-foreground', hex: '#ffffff' },
            { token: '--secondary', hex: '#f5f5f5' },
            { token: '--secondary-foreground', hex: '#1a1a1a' },
            { token: '--muted', hex: '#f5f5f5' },
            { token: '--muted-foreground', hex: '#686868' },
            { token: '--accent', hex: '#efefef' },
            { token: '--accent-foreground', hex: '#1a1a1a' },
            { token: '--border', hex: '#e3e3e3' },
            { token: '--input', hex: '#e3e3e3' },
            { token: '--ring', hex: '#8b8b85' },
        ],
    },
    {
        id: 'semantic',
        title: 'Semantic / status',
        desc: 'Status & feedback — one colour each, used sparingly. Each pairs with a -foreground (text on the solid colour) and a soft -bg tint for banners and badges.',
        swatches: [
            { token: '--success', hex: '#1b7a4b' },
            { token: '--success-foreground', hex: '#ffffff' },
            { token: '--success-bg', hex: '#e8f4ed' },
            { token: '--warning', hex: '#f08c00' },
            { token: '--warning-foreground', hex: '#422700' },
            { token: '--warning-bg', hex: '#fff3e0' },
            { token: '--destructive', hex: '#d4322b' },
            { token: '--destructive-foreground', hex: '#ffffff' },
            { token: '--destructive-bg', hex: '#fbeae9' },
            { token: '--info', hex: '#516d80', note: 'via --rom-slate' },
            { token: '--info-bg', hex: '#eef1f4', note: 'via --rom-slate-50' },
        ],
    },
    {
        id: 'sidebar',
        title: 'Sidebar',
        desc: 'A neutral charcoal rail beneath the black top bar. Inactive labels use the support gray; the active row takes the slate accent.',
        swatches: [
            { token: '--sidebar', hex: '#262626' },
            { token: '--sidebar-foreground', hex: '#f4f3f2' },
            { token: '--sidebar-muted', hex: '#8b8b85', note: 'inactive label' },
            { token: '--sidebar-accent', hex: '#383838', note: 'hover row' },
            { token: '--sidebar-active', hex: '#516d80', note: 'active row — via --rom-slate' },
            { token: '--sidebar-active-foreground', hex: '#ffffff' },
            { token: '--sidebar-border', hex: '#383838' },
        ],
    },
];

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
    <Head title="Design System" />

    <DesignSystemLayout>
        <header class="mb-12">
            <h1 class="text-3xl font-semibold tracking-tight">Design System</h1>
            <p class="text-muted-foreground mt-2 text-base">
                Internal reference for the DMV-ROM design tokens. Specimens are added here as each token slice lands.
            </p>
        </header>

        <section v-for="group in colorGroups" :key="group.id" :aria-labelledby="`${group.id}-heading`" class="mb-12">
            <h2 :id="`${group.id}-heading`" class="text-xl font-semibold tracking-tight">{{ group.title }}</h2>
            <p class="text-muted-foreground mt-1 max-w-2xl text-sm">{{ group.desc }}</p>

            <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <li v-for="swatch in group.swatches" :key="`${group.id}-${swatch.token}`" class="border-border overflow-hidden border">
                    <div class="h-20 w-full" :style="{ background: `var(${swatch.token})` }" />
                    <div class="px-3 py-2.5">
                        <code class="text-foreground text-sm font-medium">{{ swatch.token }}</code>
                        <div class="text-muted-foreground mt-0.5 font-mono text-xs uppercase">{{ swatch.hex }}</div>
                        <p v-if="swatch.note" class="text-muted-foreground mt-1.5 text-xs leading-snug">{{ swatch.note }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <section aria-labelledby="radius-heading">
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
    </DesignSystemLayout>
</template>
