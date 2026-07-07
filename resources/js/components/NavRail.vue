<script setup lang="ts">
// The grouping rail (Zone B + Zone C). Zone B leads with My Groups, then a
// collapsible Other Groups browse section; subcommittees nest under their parent.
// Zone C (Officer Tools) is pinned to the rail bottom and rendered only when its
// gated items survive — gating happens server-side, so the prop already carries
// only the items this Member may see.
import NavRailItem from '@/components/NavRailItem.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu } from '@/components/ui/sidebar';
import type { GroupNode, NavNode, RailNode } from '@/chrome/types';
import type { PhosphorIcon, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhBuildings, PhCaretDown, PhChartBar, PhChatCircleDots, PhGear, PhMegaphone } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

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
const isWithin = (href: string) => currentPath.value === href || currentPath.value.startsWith(`${href}/`);
const containsCurrent = (node: RailNode): boolean => isWithin(node.href) || ((node.children ?? []) as RailNode[]).some(containsCurrent);
const otherGroupsHasActive = computed(() => otherGroups.value.some(containsCurrent));

const otherGroupsOpen = ref(otherGroupsHasActive.value);
watch(otherGroupsHasActive, (active) => {
    if (active) otherGroupsOpen.value = true;
});
</script>

<template>
    <!-- Zone B — My Groups (lead). Omitted whole when the Member belongs to no Group. -->
    <SidebarGroup v-if="myGroupsSection" class="px-2 py-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(myGroupsSection.labelKey)
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in myGroups" :key="item.href" :item="item" :default-open="true" />
        </SidebarMenu>
    </SidebarGroup>

    <!-- Zone B — Other Groups (browse, collapsible). Collapsed by default; omitted whole
         when no visible container peer survives. -->
    <Collapsible v-if="otherGroupsSection" v-model:open="otherGroupsOpen" class="group/other-groups">
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
                        <NavRailItem v-for="item in otherGroups" :key="item.href" :item="item" />
                    </SidebarMenu>
                </SidebarGroupContent>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>

    <!-- Zone C — Officer Tools (pinned bottom). Server-pruned: present only when at
         least one item survived gating for this Member; hrefs arrive pre-localized. -->
    <SidebarGroup v-if="officerSection" class="border-sidebar-border mt-auto border-t px-2 pt-2 pb-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(officerSection.labelKey)
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in officerItems" :key="item.href" :item="item" />
        </SidebarMenu>
    </SidebarGroup>
</template>
