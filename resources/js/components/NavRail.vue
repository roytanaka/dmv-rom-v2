<script setup lang="ts">
// The grouping rail (Zone B + Zone C). Zone B leads with My Groups, then a
// collapsible Other Groups browse section; subcommittees nest under their parent.
// Zone C (Officer Tools) is pinned to the rail bottom and rendered only when its
// gated items survive — gating happens server-side, so the prop already carries
// only the items this Member may see.
import { filterRail } from '@/chrome/railFilter';
import NavRailItem from '@/components/NavRailItem.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { GroupNode, NavNode, RailNode } from '@/chrome/types';
import type { PhosphorIcon, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { PhBuildings, PhCaretDown, PhChartBar, PhChatCircleDots, PhGear, PhMegaphone } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

// The type-ahead filter query, owned by the sidebar header (AppSidebar) and threaded down
// so the flat overlay and the nested rail share one source of truth. Absent → nested rail.
const props = defineProps<{ query?: string }>();

const page = usePage<SharedData>();

// My Groups, Other Groups and Officer Tools are all server-built and server-pruned
// (PRD #209): the prop carries pre-localized rows, My Groups flat and Other Groups as the
// four organization-scope container peers (ADR-0020 §C), each exploding one level to its
// visible Groups. My Groups is omitted when the Member belongs to no Group; Other Groups
// is omitted when no visible container survives. Group rows render without an icon (every
// Group shared one placeholder, so it carried no information); custom per-Group icons
// are a later slice.
const myGroupsSection = computed(() => page.props.rail.myGroups);
const myGroups = computed<GroupNode[]>(() => myGroupsSection.value?.items ?? []);
const otherGroupsSection = computed(() => page.props.rail.otherGroups);
const otherGroups = computed<RailNode[]>(() => otherGroupsSection.value?.items ?? []);

// Officer Tools — the org-wide administration cluster, per-item gated server-side, so
// the prop carries only the items this Member may see (the cluster is absent entirely
// when none survive). Labels are i18n keys; icons are fixed client config keyed by the
// item's stable `key`, mirroring how the top bar maps its fixed destinations.
const OFFICER_ICONS: Record<string, PhosphorIcon> = {
    members: PhBuildings,
    communications: PhMegaphone,
    reports: PhChartBar,
    'flash-messages': PhChatCircleDots,
    settings: PhGear,
};
const officerSection = computed(() => page.props.rail.officer);
const officerItems = computed<NavNode[]>(() =>
    (officerSection.value?.items ?? []).map((item) => ({ labelKey: item.labelKey, href: item.href, icon: OFFICER_ICONS[item.key] })),
);

// Force Other Groups open when the active page lives inside it. The section was uncontrolled
// and re-seeded closed on every Inertia navigation (#122). Hrefs arrive pre-localized
// (ADR-0018); strip query strings to mirror NavRailItem's ancestor-aware matching.
const currentPath = computed(() => page.url.split('?')[0]);
const isWithin = (href?: string) => href !== undefined && (currentPath.value === href || currentPath.value.startsWith(`${href}/`));
const containsCurrent = (node: RailNode): boolean => isWithin(node.href) || ((node.children ?? []) as RailNode[]).some(containsCurrent);

// A stable v-for key: the localized href when the node has one, else its chrome label key
// (an href-less container peer, PRD #289). Every rail node carries one or the other.
const nodeKey = (node: RailNode) => node.href ?? ('labelKey' in node ? node.labelKey : node.name);
const otherGroupsHasActive = computed(() => otherGroups.value.some(containsCurrent));

const otherGroupsOpen = ref(otherGroupsHasActive.value);
watch(otherGroupsHasActive, (active) => {
    if (active) otherGroupsOpen.value = true;
});

// Type-ahead filter (ADR-0020 §H). When the Member types, the Group zones (My Groups +
// Other Groups) are flattened and substring-matched CLIENT-SIDE over the already-delivered,
// server-pruned prop — no network request, so it cannot surface a node the Member was not
// already sent. Clearing the box (`isFiltering` false) restores the nested rail untouched.
const isFiltering = computed(() => (props.query ?? '').trim().length > 0);
const filteredResults = computed(() => filterRail([myGroupsSection.value, otherGroupsSection.value], props.query ?? '', trans));
const isActive = (href: string) => currentPath.value === href;
</script>

<template>
    <!-- Filter overlay (ADR-0020 §H). Matches over the DELIVERED node set only — a flat list,
         each row annotated with its ancestor breadcrumb so similarly-named Groups stay apart.
         Replaces the nested rail while typing; clearing the box brings the nesting back. -->
    <SidebarGroup v-if="isFiltering" class="px-2 py-0" data-testid="rail-filter-results">
        <SidebarMenu>
            <SidebarMenuItem v-for="result in filteredResults" :key="result.href">
                <SidebarMenuButton as-child size="lg" :is-active="isActive(result.href)" class="h-auto flex-col items-start gap-0.5 py-2 text-sm">
                    <Link :href="result.href">
                        <span>{{ result.label }}</span>
                        <!-- Override the button variant's [&>span:last-child]:truncate: the breadcrumb's
                             tail (the immediate parent) is its most identifying crumb, so wrap instead
                             of clipping it. whitespace-normal! beats the variant's whitespace-nowrap. -->
                        <span v-if="result.breadcrumb.length" class="text-sidebar-muted text-xs break-words whitespace-normal!">{{
                            result.breadcrumb.join(' › ')
                        }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
        <p v-if="!filteredResults.length" class="text-sidebar-muted px-2 py-1.5 text-sm">{{ trans('nav.filter.no_results') }}</p>
    </SidebarGroup>

    <!-- Zone B — My Groups (lead). Omitted whole when the Member belongs to no Group. -->
    <SidebarGroup v-if="!isFiltering && myGroupsSection" class="px-2 py-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(myGroupsSection.labelKey)
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in myGroups" :key="item.href" :item="item" :default-open="true" />
        </SidebarMenu>
    </SidebarGroup>

    <!-- Zone B — Other Groups (browse, collapsible). Collapsed by default; omitted whole
         when no visible container peer survives. -->
    <Collapsible v-if="!isFiltering && otherGroupsSection" v-model:open="otherGroupsOpen" class="group/other-groups">
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel as-child>
                <CollapsibleTrigger
                    class="text-sidebar-muted hover:bg-sidebar-accent hover:text-sidebar-foreground flex w-full items-center justify-between text-xs font-semibold tracking-wide uppercase"
                >
                    {{ trans(otherGroupsSection.labelKey) }}
                    <PhCaretDown class="size-4 transition-transform group-data-[state=open]/other-groups:rotate-180" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <NavRailItem v-for="item in otherGroups" :key="nodeKey(item)" :item="item" />
                    </SidebarMenu>
                </SidebarGroupContent>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>

    <!-- Zone C — Officer Tools (pinned bottom). Server-pruned: present only when at
         least one item survived gating for this Member; hrefs arrive pre-localized. -->
    <SidebarGroup v-if="!isFiltering && officerSection" class="border-sidebar-border mt-auto border-t px-2 pt-2 pb-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(officerSection.labelKey)
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in officerItems" :key="item.href" :item="item" />
        </SidebarMenu>
    </SidebarGroup>
</template>
