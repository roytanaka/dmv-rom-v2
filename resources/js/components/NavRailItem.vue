<script setup lang="ts">
// One rail row (grouping nav). A node with children (e.g. a Group with
// subcommittees) renders its children nested beneath it; a leaf renders a single
// link. Labels come from i18n keys; gating is applied to children here.
import { SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { visibleNodes } from '@/chrome/gating';
import { t } from '@/chrome/messages';
import type { NavNode } from '@/chrome/types';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ item: NavNode }>();

const page = usePage<SharedData>();
const isActive = (href: string) => href === page.url;

// Subcommittees nest under their active parent; re-filter through the (stubbed) gate.
const children = computed(() => (props.item.children ? visibleNodes(props.item.children) : []));
</script>

<template>
    <SidebarMenuItem>
        <SidebarMenuButton as-child size="lg" :is-active="isActive(item.href)" class="text-base">
            <a v-if="item.external" :href="item.href" target="_blank" rel="noopener noreferrer">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ t(item.labelKey) }}</span>
            </a>
            <Link v-else :href="item.href">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ t(item.labelKey) }}</span>
            </Link>
        </SidebarMenuButton>

        <SidebarMenuSub v-if="children.length">
            <SidebarMenuSubItem v-for="child in children" :key="child.href">
                <SidebarMenuSubButton as-child :is-active="isActive(child.href)" class="text-sm">
                    <Link :href="child.href">
                        <span>{{ t(child.labelKey) }}</span>
                    </Link>
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
        </SidebarMenuSub>
    </SidebarMenuItem>
</template>
