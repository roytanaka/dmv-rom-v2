<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DesignSystemLayout from '@/layouts/DesignSystemLayout.vue';
import { Head } from '@inertiajs/vue3';
import { PhPlus } from '@phosphor-icons/vue';

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

// Type-scale specimens. Each row renders sample text at the live `text-*`
// utility (size + coupled line-height from the remapped --text-* ramp) and
// labels it with the px size and role. Values mirror resources/css/app.css —
// the scale is verified by eye here, not asserted in tests (PRD #37).
type TypeStep = { name: string; px: number; lh: string; class: string; role: string };

const typeScale: TypeStep[] = [
    { name: 'text-5xl', px: 54, lh: '1.2', class: 'text-5xl', role: 'hero / login title' },
    { name: 'text-4xl', px: 42, lh: '1.2', class: 'text-4xl', role: 'page title (h1)' },
    { name: 'text-3xl', px: 34, lh: '1.2', class: 'text-3xl', role: 'page heading (h2)' },
    { name: 'text-2xl', px: 28, lh: '1.35', class: 'text-2xl', role: 'section heading (h3)' },
    { name: 'text-xl', px: 23, lh: '1.35', class: 'text-xl', role: 'card title (h4)' },
    { name: 'text-lg', px: 20, lh: '1.55', class: 'text-lg', role: 'lead paragraph' },
    { name: 'text-base', px: 18, lh: '1.55', class: 'text-base', role: 'body — the default' },
    { name: 'text-sm', px: 15, lh: '1.55', class: 'text-sm', role: 'secondary UI, cells' },
    { name: 'text-xs', px: 13, lh: '1.55', class: 'text-xs', role: 'micro-labels, timestamps' },
];

// Element specimens — bare <h1>–<h4> and the .eyebrow / .caption helpers prove
// the @layer base element defaults render correctly with no utility classes.
const headingSpecimens = [
    { tag: 'h1', spec: '42 · 700', text: 'Museum Volunteers' },
    { tag: 'h2', spec: '34 · 700', text: 'July, 2026 Sign up' },
    { tag: 'h3', spec: '28 · 600', text: 'Scheduled Tours' },
    { tag: 'h4', spec: '23 · 600', text: 'Card title' },
] as const;

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

// Component-gallery specimens. This section is established here (PRD #44, slice
// #47) and grown by later slices. Each row renders one Button variant across the
// sm / default / lg / icon sizes plus a disabled state — squareness and the
// bumped size scale are verified by eye, not asserted in tests.
type ButtonVariant = NonNullable<ButtonVariants['variant']>;
const buttonRows: { variant: ButtonVariant; label: string }[] = [
    { variant: 'default', label: 'Default' },
    { variant: 'destructive', label: 'Destructive' },
    { variant: 'outline', label: 'Outline' },
    { variant: 'secondary', label: 'Secondary' },
    { variant: 'ghost', label: 'Ghost' },
    { variant: 'link', label: 'Link' },
];
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

        <section aria-labelledby="type-heading" class="mb-12">
            <h2 id="type-heading" class="text-xl font-semibold tracking-tight">Typography</h2>
            <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                One native system-font stack, no webfont. The scale is sized up for the DMV’s retiree volunteers — body is
                <strong>18px</strong>; persistent UI text never drops below <strong>15px</strong>. Each step carries a coupled line-height (body 1.55,
                headings 1.2–1.35).
            </p>

            <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Scale</h3>
            <ul class="border-border mt-4 divide-y border-y">
                <li v-for="step in typeScale" :key="step.name" class="flex items-baseline gap-6 py-3">
                    <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ step.name }}</code>
                    <span :class="[step.class, 'min-w-0 flex-1 truncate font-medium']">The quick brown fox</span>
                    <span class="text-muted-foreground flex-none font-mono text-xs">{{ step.px }}px · {{ step.lh }}</span>
                    <span class="text-muted-foreground hidden w-44 flex-none text-xs sm:block">{{ step.role }}</span>
                </li>
            </ul>

            <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Headings</h3>
            <p class="text-muted-foreground mt-1 text-sm">
                Bare <code>h1</code>–<code>h4</code> elements — the <code>@layer base</code> defaults, no utility classes.
            </p>
            <dl class="mt-4 space-y-4">
                <div v-for="h in headingSpecimens" :key="h.tag" class="flex items-baseline gap-6">
                    <component :is="h.tag" class="min-w-0 flex-1">{{ h.text }}</component>
                    <span class="text-muted-foreground flex-none font-mono text-xs">{{ h.tag }} · {{ h.spec }}</span>
                </div>
            </dl>

            <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Body &amp; helpers</h3>
            <div class="mt-4 max-w-2xl space-y-4">
                <p class="text-lg">Lead paragraph — sign up for a maximum of three tours this month.</p>
                <p class="text-base">
                    Body copy at 18px with a roomy 1.55 line-height. Enter your email and password below to log in to your account, then choose the
                    tours you would like to lead.
                </p>
                <p class="text-sm">Secondary UI · 01 WED @ 11:00 — Museum Highlights</p>
                <p class="text-xs">Micro-label · last updated 2 hours ago</p>
                <p class="eyebrow">Department of Museum Volunteers</p>
                <p class="caption">Caption — sized at 15px in the muted foreground tone.</p>
            </div>
        </section>

        <section aria-labelledby="radius-heading" class="mb-12">
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

        <section aria-labelledby="components-heading">
            <h2 id="components-heading" class="text-xl font-semibold tracking-tight">Components</h2>
            <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                Customised <code>shadcn-vue</code> primitives. Corners are square (<code>rounded-none</code>) by default and the size scale is bumped
                for the DMV’s audience, floored at 15px. Each component is added here as its slice lands.
            </p>

            <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Button</h3>
            <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                The six variants across the <code>sm</code> / <code>default</code> / <code>lg</code> / <code>icon</code> sizes, with a disabled state.
                The <code>link</code> variant carries the heritage-blue accent.
            </p>

            <div class="mt-6 space-y-5">
                <div v-for="row in buttonRows" :key="row.variant" class="flex flex-wrap items-center gap-4">
                    <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ row.variant }}</code>
                    <Button :variant="row.variant" size="sm">{{ row.label }}</Button>
                    <Button :variant="row.variant">{{ row.label }}</Button>
                    <Button :variant="row.variant" size="lg">{{ row.label }}</Button>
                    <Button :variant="row.variant" size="icon" :aria-label="`${row.label} icon`">
                        <PhPlus class="size-4" />
                    </Button>
                    <Button :variant="row.variant" disabled>{{ row.label }}</Button>
                </div>
            </div>

            <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Form inputs</h3>
            <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                <code>Input</code> is square (<code>rounded-none</code>), 44px tall, and holds 18px text at every breakpoint. Focus is the one
                heritage-blue exception — a <code>rom-slate</code> border with a soft <code>rom-slate-50</code> glow — while the error state is driven
                by the <code>aria-invalid</code> attribute, not a custom prop. <code>Label</code> stays 15px / medium and
                <code>InputError</code> renders on the <code>destructive</code> token.
            </p>

            <div class="mt-6 grid max-w-2xl gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ds-input-default">Default</Label>
                    <Input id="ds-input-default" placeholder="Volunteer name" />
                </div>
                <div class="grid gap-2">
                    <Label for="ds-input-focus">Focus</Label>
                    <Input id="ds-input-focus" placeholder="Volunteer name" class="border-rom-slate ring-rom-slate-50 ring-2" />
                    <p class="text-muted-foreground text-sm">Shown statically — the resting <code>:focus</code> state needs interaction to render.</p>
                </div>
                <div class="grid gap-2">
                    <Label for="ds-input-error">Error</Label>
                    <Input id="ds-input-error" aria-invalid="true" default-value="not-an-email" />
                    <InputError message="Enter a valid email address." />
                </div>
                <div class="grid gap-2">
                    <Label for="ds-input-disabled">Disabled</Label>
                    <Input id="ds-input-disabled" placeholder="Volunteer name" disabled />
                </div>
            </div>
        </section>
    </DesignSystemLayout>
</template>
