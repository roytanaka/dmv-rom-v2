<script setup lang="ts">
// One rail row (grouping nav). A leaf renders a single link. A node with children
// (e.g. a Group with subcommittees) renders a SPLIT row: the label is a link that
// navigates into the Group, and a separate chevron toggles its children open/closed
// WITHOUT navigating — decoupling the two actions the legacy app fused into one click.
// Open state seeds from the section default (`defaultOpen`), force-opens whenever the
// Group or one of its children is the current page (so you can see where you are), and
// is otherwise driven by the chevron. Labels come from i18n keys; gating filters children.
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { visibleNodes } from '@/chrome/gating';
import { t } from '@/chrome/messages';
import type { NavNode } from '@/chrome/types';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { PhCaretDown } from '@phosphor-icons/vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ item: NavNode; defaultOpen?: boolean }>(), { defaultOpen: false });

const page = usePage<SharedData>();
const isActive = (href: string) => href === page.url;

// Subcommittees nest under their active parent; re-filter through the (stubbed) gate.
const children = computed(() => (props.item.children ? visibleNodes(props.item.children) : []));

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
                <span>{{ t(item.labelKey) }}</span>
            </a>
            <Link v-else :href="item.href">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ t(item.labelKey) }}</span>
            </Link>
        </SidebarMenuButton>
    </SidebarMenuItem>

    <!-- Parent — link to navigate + chevron to toggle children. -->
    <Collapsible v-else v-model:open="open" as-child class="group/collapsible">
        <SidebarMenuItem>
            <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" class="h-10 text-sm">
                <Link :href="item.href">
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ t(item.labelKey) }}</span>
                </Link>
            </SidebarMenuButton>

            <CollapsibleTrigger as-child>
                <!-- top-0! overrides the primitive's peer-data-[size=lg]/menu-button:top-2.5 variant;. -->
                <SidebarMenuAction class="top-0! aspect-auto h-10 w-8 translate-x-1">
                    <PhCaretDown class="transition-transform group-data-[state=open]/collapsible:rotate-180" />
                    <span class="sr-only">{{ t(item.labelKey) }}</span>
                </SidebarMenuAction>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <SidebarMenuSub>
                    <SidebarMenuSubItem v-for="child in children" :key="child.href">
                        <SidebarMenuSubButton as-child :is-active="isActive(child.href)" class="text-sm">
                            <Link :href="child.href">
                                <span>{{ t(child.labelKey) }}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
</template>
