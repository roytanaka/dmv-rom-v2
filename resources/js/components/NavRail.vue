<script setup lang="ts">
// The grouping rail (Zone B + Zone C). Zone B leads with My Groups, then a
// collapsible All Groups browse section; subcommittees nest under their parent.
// Zone C (Officer Tools) is pinned to the rail bottom and rendered only when its
// gated items survive — gating happens server-side, so the prop already carries
// only the items this Member may see.
import NavRailItem from '@/components/NavRailItem.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu } from '@/components/ui/sidebar';
import type { GroupNode, NavNode } from '@/chrome/types';
import type { PhosphorIcon, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhBuildings, PhCaretDown, PhChartBar, PhChatCircleDots, PhGear, PhMegaphone } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const page = usePage<SharedData>();

// My Groups, All Groups and Officer Tools are all server-built and server-pruned
// (PRD #209): the prop carries pre-localized rows, My Groups flat and All Groups as the
// active org tree already reshaped (Option C) with subcommittees nested. My Groups is
// omitted when the Member belongs to no Group; All Groups, present for every Member, is
// omitted only when no active Group exists. Group rows render without an icon (every
// Group shared one placeholder, so it carried no information); custom per-Group icons
// are a later slice.
const myGroupsSection = computed(() => page.props.rail.myGroups);
const myGroups = computed<GroupNode[]>(() => myGroupsSection.value?.items ?? []);
const allGroupsSection = computed(() => page.props.rail.allGroups);
const allGroups = computed<GroupNode[]>(() => allGroupsSection.value?.items ?? []);

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

// Force All Groups open when the active page lives inside it. The section was uncontrolled
// and re-seeded closed on every Inertia navigation (#122). Hrefs arrive pre-localized
// (ADR-0018); strip query strings to mirror NavRailItem's ancestor-aware matching.
const currentPath = computed(() => page.url.split('?')[0]);
const isWithin = (href: string) => currentPath.value === href || currentPath.value.startsWith(`${href}/`);
const containsCurrent = (group: GroupNode): boolean => isWithin(group.href) || (group.children ?? []).some(containsCurrent);
const allGroupsHasActive = computed(() => allGroups.value.some(containsCurrent));

const allGroupsOpen = ref(allGroupsHasActive.value);
watch(allGroupsHasActive, (active) => {
    if (active) allGroupsOpen.value = true;
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

    <!-- Zone B — All Groups (browse, collapsible). Collapsed by default; omitted whole
         only when no active Group exists. -->
    <Collapsible v-if="allGroupsSection" v-model:open="allGroupsOpen" class="group/all-groups">
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel as-child>
                <CollapsibleTrigger
                    class="text-sidebar-muted hover:bg-sidebar-accent hover:text-sidebar-foreground flex w-full items-center justify-between text-xs font-semibold tracking-wide uppercase"
                >
                    {{ trans(allGroupsSection.labelKey) }}
                    <PhCaretDown class="size-4 transition-transform group-data-[state=open]/all-groups:rotate-180" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <NavRailItem v-for="item in allGroups" :key="item.href" :item="item" />
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
