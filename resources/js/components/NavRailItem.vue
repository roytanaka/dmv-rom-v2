<script setup lang="ts">
// One rail row (grouping nav), rendered RECURSIVELY so the rail honours whatever
// depth the server sends (ADR-0020 §D: the builder emits the full pruned tree, so a
// super-tier viewer — or a deliberately `Public` sub-group — surfaces a program's
// working groups beneath it). A leaf renders a single link. A node with children
// renders a SPLIT row: the label is a link that navigates into the Group, and a
// separate chevron toggles its children open/closed WITHOUT navigating — decoupling
// the two actions the legacy app fused into one click. Each child is itself a
// NavRailItem one level deeper, so nesting is unbounded rather than capped at two.
//
// Depth drives the row chrome: depth 0 is a full-size top row (My Groups items and
// the Other Groups container peers); depth ≥ 1 is a compact sub-row. Indentation is
// a single left-only step per level (see SUB_INDENT), tightened from the shadcn
// default so a third tier stays comfortably inside the 16rem rail.
//
// Open state seeds from the section default (`defaultOpen`), force-opens whenever the
// node or anything in its subtree is the current page (so you can see where you are),
// and is otherwise driven by the chevron. The server prunes the rail (ADR-0018), so
// the children handed to this row are already only the ones the Member may see.
//
// A rail row is either a structural NavNode (the officer cluster / a container peer —
// translated `labelKey`) or a content GroupNode (a Group — as-authored `name`,
// ADR-0004). `label()` branches on which kind a node is, so Group names never enter
// the translation lookup while structural labels always do.
import NavRailItem from '@/components/NavRailItem.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import type { RailNode } from '@/chrome/types';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { PhCaretDown } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ item: RailNode; defaultOpen?: boolean; depth?: number }>(), {
    defaultOpen: false,
    depth: 0,
});

// One left-only indent step per level, overriding the shadcn SidebarMenuSub default
// (`mx-3.5 px-2.5`, ~24px and symmetric so it also eats the right edge). tailwind-merge
// keeps these last-wins: ~16px left step, no wasted right inset, border-l tree-guide kept.
const SUB_INDENT = 'mx-0 ml-2 pl-2 pr-0';

const page = usePage<SharedData>();
// Rail hrefs arrive pre-localized (ADR-0018) — used verbatim. Strip the query string:
// section pages carry ?tab=… that rail hrefs never do.
const currentPath = computed(() => page.url.split('?')[0]);

// Strong active — only the deepest matching node earns this; ancestors get the softer contains-active state (#122).
const isActive = (href: string) => currentPath.value === href;

// Ancestor-aware — the current page is this node or nested beneath it. A section page
// (/groups/docents/data-sheets) lives under the Group's /groups/docents href, so the
// Group still counts as "in context" even though no node matches the path exactly.
const isWithin = (href: string) => currentPath.value === href || currentPath.value.startsWith(`${href}/`);

// Group name (content) → verbatim; structural node → translated label (chrome).
const label = (node: RailNode) => ('name' in node ? node.name : trans(node.labelKey));

// Subcommittees nest under their parent. The server prunes the rail (ADR-0018), so
// children arrive pre-filtered; widen to RailNode[] so `label()` resolves them the
// same way as their parent.
const children = computed<RailNode[]>(() => (props.item.children ?? []) as RailNode[]);

// "In context" — this node or anything in its subtree contains the current page. Walked
// recursively (not just direct children) so the whole ancestor chain force-opens down to
// a deeply-nested active page. Drives force-open and the soft contains-active state.
const containsCurrent = (node: RailNode): boolean => isWithin(node.href) || ((node.children ?? []) as RailNode[]).some(containsCurrent);
const hasActiveDescendant = computed(() => containsCurrent(props.item));

// Soft highlight: active page is nested inside but this node isn't the exact match.
const containsActive = computed(() => hasActiveDescendant.value && !isActive(props.item.href));

// Open state: seeded from the section default, force-open while in context, and freely
// toggled by the chevron afterwards. Navigating in reveals it; the user can still close it.
const open = ref(props.defaultOpen || hasActiveDescendant.value);
watch(hasActiveDescendant, (active) => {
    if (active) open.value = true;
});
</script>

<template>
    <!-- ── Top level (depth 0): full-size rows — My Groups items and Other Groups peers. ── -->
    <template v-if="depth === 0">
        <!-- Leaf — a single navigable row. -->
        <SidebarMenuItem v-if="!children.length">
            <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" :class="['h-10 text-sm', { 'font-medium': containsActive }]">
                <a v-if="item.external" :href="item.href" target="_blank" rel="noopener noreferrer">
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ label(item) }}</span>
                </a>
                <Link v-else :href="item.href">
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ label(item) }}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>

        <!-- Parent — link to navigate + chevron to toggle children. -->
        <Collapsible v-else v-model:open="open" as-child class="group/collapsible">
            <SidebarMenuItem>
                <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" :class="['h-10 text-sm', { 'font-medium': containsActive }]">
                    <Link :href="item.href">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ label(item) }}</span>
                    </Link>
                </SidebarMenuButton>

                <CollapsibleTrigger as-child>
                    <!-- top-0! overrides the primitive's peer-data-[size=lg]/menu-button:top-2.5 variant;. -->
                    <SidebarMenuAction class="top-0! aspect-auto h-10 w-8 translate-x-1">
                        <PhCaretDown class="transition-transform group-data-[state=open]/collapsible:rotate-180" />
                        <!-- #91: gives the chevron a distinct accessible label ("Toggle Docents subgroups")
                             so it reads differently from the sibling nav link in the same row. -->
                        <span class="sr-only">{{ trans('nav.toggle', { group: label(item) }) }}</span>
                    </SidebarMenuAction>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub :class="SUB_INDENT">
                        <NavRailItem v-for="child in children" :key="child.href" :item="child" :depth="depth + 1" />
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    </template>

    <!-- ── Sub level (depth ≥ 1): compact rows. A parent carries its own chevron so the
         tree can expand past two levels (the reason sub-rows needed rebuilding). ── -->
    <template v-else>
        <!-- Leaf sub-row. -->
        <SidebarMenuSubItem v-if="!children.length">
            <SidebarMenuSubButton
                as-child
                :is-active="isActive(item.href)"
                :class="['text-sm', { 'font-medium': isWithin(item.href) && !isActive(item.href) }]"
            >
                <Link :href="item.href">
                    <span>{{ label(item) }}</span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>

        <!-- Parent sub-row: label link + a separate chevron. The chevron is positioned
             against an inner `relative` wrapper, NOT the collapsible <li>: radix's as-child
             merge clobbers a class set on the <li> itself (it drops `relative`), which would
             leave the absolute chevron anchored to a distant ancestor — stacking every
             sub-row's chevron at one spot. The inner div keeps the anchor local and safe. -->
        <Collapsible v-else v-model:open="open" as-child class="group/collapsible">
            <SidebarMenuSubItem>
                <div class="relative">
                    <SidebarMenuSubButton as-child :is-active="isActive(item.href)" :class="['pr-8 text-sm', { 'font-medium': containsActive }]">
                        <Link :href="item.href">
                            <span>{{ label(item) }}</span>
                        </Link>
                    </SidebarMenuSubButton>

                    <CollapsibleTrigger as-child>
                        <button
                            type="button"
                            class="text-sidebar-foreground ring-sidebar-ring hover:bg-sidebar-accent hover:text-sidebar-accent-foreground absolute top-1 right-1 flex aspect-square w-5 items-center justify-center rounded-md outline-hidden focus-visible:ring-2"
                        >
                            <PhCaretDown class="size-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                            <span class="sr-only">{{ trans('nav.toggle', { group: label(item) }) }}</span>
                        </button>
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent>
                    <SidebarMenuSub :class="SUB_INDENT">
                        <NavRailItem v-for="child in children" :key="child.href" :item="child" :depth="depth + 1" />
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuSubItem>
        </Collapsible>
    </template>
</template>
