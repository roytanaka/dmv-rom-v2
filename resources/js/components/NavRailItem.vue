<script setup lang="ts">
// One rail row (grouping nav). A leaf renders a single link. A node with children
// (e.g. a Group with subcommittees) renders a SPLIT row: the label is a link that
// navigates into the Group, and a separate chevron toggles its children open/closed
// WITHOUT navigating — decoupling the two actions the legacy app fused into one click.
// Open state seeds from the section default (`defaultOpen`), force-opens whenever the
// Group or one of its children is the current page (so you can see where you are), and
// is otherwise driven by the chevron. The server prunes the rail (ADR-0018), so the
// children handed to this row are already only the ones the Member may see.
//
// A rail row is either a structural NavNode (the officer cluster — translated
// `labelKey`) or a content GroupNode (a Group — as-authored `name`, ADR-0004).
// `label()` branches on which kind a node is, so Group names never enter the
// translation lookup while structural labels always do.
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

const props = withDefaults(defineProps<{ item: RailNode; defaultOpen?: boolean }>(), {
    defaultOpen: false,
});

const page = usePage<SharedData>();
// Rail hrefs arrive pre-localized from the server (ADR-0018), mirroring chromeNav, so
// they are used verbatim — no client-side locale step — and match the active row
// under /fr/ without double-prefixing.
const isActive = (href: string) => href === page.url;

// Group name (content) → verbatim; structural node → translated label (chrome).
const label = (node: RailNode) => ('name' in node ? node.name : trans(node.labelKey));

// Subcommittees nest under their active parent. The server prunes the rail (ADR-0018),
// so children arrive pre-filtered; widen to RailNode[] so `label()` resolves them the
// same way as their parent.
const children = computed<RailNode[]>(() => (props.item.children ?? []) as RailNode[]);

// "In context" — this Group or one of its visible subgroups is the current page.
const hasActiveDescendant = computed(() => isActive(props.item.href) || children.value.some((child) => isActive(child.href)));

// Open state: seeded from the section default, force-open while in context, and freely
// toggled by the chevron afterwards. Navigating in reveals it; the user can still close it.
const open = ref(props.defaultOpen || hasActiveDescendant.value);
watch(hasActiveDescendant, (active) => {
    if (active) open.value = true;
});
</script>

<template>
    <!-- Leaf — a single navigable row. -->
    <SidebarMenuItem v-if="!children.length">
        <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" class="h-10 text-sm">
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
            <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" class="h-10 text-sm">
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
                <SidebarMenuSub>
                    <SidebarMenuSubItem v-for="child in children" :key="child.href">
                        <SidebarMenuSubButton as-child :is-active="isActive(child.href)" class="text-sm">
                            <Link :href="child.href">
                                <span>{{ label(child) }}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
</template>
