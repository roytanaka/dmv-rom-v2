<script setup lang="ts">
import DesignNote from '@/components/DesignNote.vue';
import AppShellSection from '@/components/design-system/sections/AppShellSection.vue';
import AvatarSection from '@/components/design-system/sections/AvatarSection.vue';
import BreadcrumbSection from '@/components/design-system/sections/BreadcrumbSection.vue';
import CollapsibleSection from '@/components/design-system/sections/CollapsibleSection.vue';
import ColorSection from '@/components/design-system/sections/ColorSection.vue';
import ComponentsSection from '@/components/design-system/sections/ComponentsSection.vue';
import ElevationSection from '@/components/design-system/sections/ElevationSection.vue';
import NavigationMenuSection from '@/components/design-system/sections/NavigationMenuSection.vue';
import RadiusSection from '@/components/design-system/sections/RadiusSection.vue';
import SeparatorSection from '@/components/design-system/sections/SeparatorSection.vue';
import SheetSection from '@/components/design-system/sections/SheetSection.vue';
import SpacingSection from '@/components/design-system/sections/SpacingSection.vue';
import TypographySection from '@/components/design-system/sections/TypographySection.vue';
import DesignSystemLayout from '@/layouts/DesignSystemLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// In-page wayfinding. The single source of truth for the sticky section rail;
// each entry's `id` is the stable, deep-linkable anchor on the matching
// `<section>` element rendered by one of the section components below. Append
// here as later slices add sections.
const sections = [
    { id: 'brand', label: 'Brand' },
    { id: 'neutral', label: 'Neutral' },
    { id: 'semantic', label: 'Semantic' },
    { id: 'sidebar', label: 'Sidebar' },
    { id: 'typography', label: 'Typography' },
    { id: 'radius', label: 'Radius' },
    { id: 'elevation', label: 'Elevation' },
    { id: 'spacing', label: 'Spacing' },
    { id: 'components', label: 'Components' },
    { id: 'avatar', label: 'Avatar' },
    { id: 'breadcrumb', label: 'Breadcrumb' },
    { id: 'collapsible', label: 'Collapsible' },
    { id: 'navigation-menu', label: 'Navigation menu' },
    { id: 'separator', label: 'Separator' },
    { id: 'sheet', label: 'Sheet' },
    { id: 'app-shell', label: 'App shell' },
] as const;

// Scrollspy: highlight the rail entry for the section currently in view. The
// rootMargin biases the "active" band to the upper third of the viewport so a
// heading lights up as it reaches the top, not the middle. The observed elements
// are the `<section id>`s rendered by the section components, found by id after
// they mount.
const activeSection = ref<string>(sections[0].id);
let observer: IntersectionObserver | undefined;

onMounted(() => {
    const hash = window.location.hash.slice(1);
    if (sections.some((s) => s.id === hash)) activeSection.value = hash;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) activeSection.value = entry.target.id;
            }
        },
        { rootMargin: '-10% 0px -80% 0px' },
    );

    for (const section of sections) {
        const el = document.getElementById(section.id);
        if (el) observer.observe(el);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Design System" />

    <DesignSystemLayout>
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight">Design System</h1>
            <p class="text-muted-foreground mt-2 text-base">
                Internal reference for the DMV-ROM design tokens. Specimens are added here as each token slice lands.
            </p>
            <DesignNote class="mt-6" title="Snippets are illustrative">
                The usage snippets beside each component show a typical import and a minimal example. They are deliberately short and are
                <strong>not</strong> an API contract — for the authoritative props and slots, read the component source under
                <code>@/components/ui</code>.
            </DesignNote>
        </header>

        <div class="lg:grid lg:grid-cols-[10rem_minmax(0,1fr)] lg:gap-x-12 xl:gap-x-16">
            <!-- Sticky in-page wayfinding: a horizontal scroller below lg, a vertical rail at lg+. -->
            <nav
                aria-label="Page sections"
                class="bg-background/90 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-20 -mx-6 mb-8 border-b backdrop-blur md:-mx-10 lg:top-12 lg:z-0 lg:mx-0 lg:mb-0 lg:self-start lg:border-0 lg:bg-transparent lg:backdrop-blur-none"
            >
                <ul class="flex gap-x-1 overflow-x-auto px-6 py-3 md:px-10 lg:flex-col lg:gap-x-0 lg:gap-y-0.5 lg:overflow-visible lg:px-0 lg:py-0">
                    <li v-for="section in sections" :key="section.id" class="flex-none">
                        <a
                            :href="`#${section.id}`"
                            :aria-current="activeSection === section.id ? 'location' : undefined"
                            :class="[
                                'block border-l-2 px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                activeSection === section.id
                                    ? 'border-rom-slate text-rom-slate font-medium'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
                            ]"
                        >
                            {{ section.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="min-w-0">
                <ColorSection />
                <TypographySection />
                <RadiusSection />
                <ElevationSection />
                <SpacingSection />
                <ComponentsSection />
                <AvatarSection />
                <BreadcrumbSection />
                <CollapsibleSection />
                <NavigationMenuSection />
                <SeparatorSection />
                <SheetSection />
                <AppShellSection />
            </div>
        </div>
    </DesignSystemLayout>
</template>
