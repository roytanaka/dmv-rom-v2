<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { NavigationMenu, NavigationMenuItem, NavigationMenuLink, NavigationMenuList } from '@/components/ui/navigation-menu';
import { snippets } from '../snippets';

// NavigationMenu specimen — mirrors the top-bar section nav (SectionTabs): a
// horizontal strip of section links on the black bar, exactly one active in the
// heritage-blue accent with the bottom-border cue. Shown on a `bg-rom-ink`
// surface because that is the context the section nav actually lives in.
const navMenuItems = [
    { label: 'Dashboard', active: false },
    { label: 'Tours', active: true },
    { label: 'Volunteers', active: false },
    { label: 'Reports', active: false },
];
</script>

<template>
    <section id="navigation-menu" aria-labelledby="navigation-menu-heading" class="mb-12 scroll-mt-24">
        <h2 id="navigation-menu-heading" class="text-xl font-semibold tracking-tight">Navigation menu</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Shown in the form the top-bar section nav uses it — a horizontal strip of section links on the black bar, with exactly one active in the
            heritage-blue accent (<code>rom-slate-300</code>) carrying the bottom-border cue. Rendered on the
            <code>rom-ink</code> surface because that is the context it lives in.
        </p>

        <div class="bg-rom-ink mt-6 flex px-4 py-3">
            <NavigationMenu>
                <NavigationMenuList class="gap-1">
                    <NavigationMenuItem v-for="item in navMenuItems" :key="item.label">
                        <NavigationMenuLink
                            href="#navigation-menu"
                            :active="item.active"
                            :aria-current="item.active ? 'page' : undefined"
                            :class="[
                                'flex items-center border-b-2 px-3 py-1 text-sm whitespace-nowrap transition-colors',
                                item.active ? 'border-rom-slate-300 text-rom-slate-300' : 'border-transparent text-white/70 hover:text-white',
                            ]"
                        >
                            {{ item.label }}
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>
        </div>
        <CodeSnippet :value="snippets.navigationMenu" />
    </section>
</template>
