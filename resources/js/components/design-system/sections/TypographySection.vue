<script setup lang="ts">
import DesignNote from '@/components/DesignNote.vue';

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
    { name: 'text-sm', px: 16, lh: '1.55', class: 'text-sm', role: 'secondary UI, cells' },
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
</script>

<template>
    <section id="typography" aria-labelledby="type-heading" class="mb-12 scroll-mt-24">
        <h2 id="type-heading" class="text-xl font-semibold tracking-tight">Typography</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            One native system-font stack, no webfont. The scale is sized up for the DMV’s retiree volunteers — body is
            <strong>18px</strong>; persistent UI text never drops below <strong>16px</strong>. Each step carries a coupled line-height (body 1.55,
            headings 1.2–1.35).
        </p>
        <DesignNote variant="dont" title="Don’t go below 16px">
            The audience is the DMV’s retiree volunteers, so persistent UI text — labels, table cells, secondary copy — is floored at
            <strong>16px</strong> (<code>text-sm</code>). Reach for <code>text-xs</code> (13px) only for incidental micro-labels like timestamps,
            never for content a volunteer has to act on.
        </DesignNote>
        <DesignNote title="Sized in rem, not px">
            Every step is expressed in <code>rem</code> against the 16px root, so the whole scale honours the browser’s own font-size preference and
            is ready to grow or shrink from a single root change — the hook for a future text-size control. The px values shown here are the
            equivalents at the default root. See <a href="/docs/adr/0014-design-token-decisions.md" class="underline">ADR-0014</a>.
        </DesignNote>

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
                Body copy at 18px with a roomy 1.55 line-height. Enter your email and password below to log in to your account, then choose the tours
                you would like to lead.
            </p>
            <p class="text-sm">Secondary UI · 01 WED @ 11:00 — Museum Highlights</p>
            <p class="text-xs">Micro-label · last updated 2 hours ago</p>
            <p class="eyebrow">Department of Museum Volunteers</p>
            <p class="caption">Caption — sized at 16px in the muted foreground tone.</p>
        </div>
    </section>
</template>
