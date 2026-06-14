<script setup lang="ts">
// The grouping rail (Zone B + Zone C). Zone B leads with My Groups, then a
// collapsible All Groups browse section; subcommittees nest under their parent.
// Zone C (officer/admin) is pinned to the rail bottom and rendered only when its
// gated items survive (stubbed show-all → always, for now).
import NavRailItem from '@/components/NavRailItem.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu } from '@/components/ui/sidebar';
import { visibleNodes } from '@/chrome/gating';
import { railNav } from '@/chrome/fixture';
import { PhCaretDown } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const myGroups = computed(() => visibleNodes(railNav.myGroups.items));
const allGroups = computed(() => visibleNodes(railNav.allGroups.items));
const officerItems = computed(() => visibleNodes(railNav.officer.items));
</script>

<template>
    <!-- Zone B — My Groups (lead) -->
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(railNav.myGroups.labelKey ?? '')
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in myGroups" :key="item.href" :item="item" :default-open="true" />
        </SidebarMenu>
    </SidebarGroup>

    <!-- Zone B — All Groups (browse, collapsible) -->
    <Collapsible :default-open="railNav.allGroups.defaultOpen" class="group/all-groups">
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel as-child>
                <CollapsibleTrigger
                    class="text-sidebar-muted hover:bg-sidebar-accent hover:text-sidebar-foreground flex w-full items-center justify-between text-xs font-semibold tracking-wide uppercase"
                >
                    {{ trans(railNav.allGroups.labelKey ?? '') }}
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

    <!-- Zone C — officer/admin (pinned bottom, officer-only) -->
    <SidebarGroup v-if="officerItems.length" class="mt-auto px-2 py-0">
        <SidebarGroupLabel class="text-sidebar-muted text-xs font-semibold tracking-wide uppercase">{{
            trans(railNav.officer.labelKey ?? '')
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavRailItem v-for="item in officerItems" :key="item.href" :item="item" />
        </SidebarMenu>
    </SidebarGroup>
</template>
