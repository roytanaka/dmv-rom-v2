<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppFooter from '@/components/AppFooter.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationToolbar from '@/components/ImpersonationToolbar.vue';
import TopBar from '@/components/TopBar.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell>
        <!-- Full-width top bar spans above both the rail and the content. Sticky so it
             stays pinned while the page scrolls and the fixed rail (offset below it)
             remains aligned. -->
        <TopBar class="sticky top-0 z-30" />
        <div class="flex w-full flex-1">
            <AppSidebar />
            <AppContent>
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <slot />
                <AppFooter />
            </AppContent>
        </div>
        <!-- The floating dev/QA role-switcher. Renders only when the server ships the
             `impersonation` prop (non-prod, super-tier or an active session); null
             otherwise, so it is absent in production and for ordinary Members. -->
        <ImpersonationToolbar />
    </AppShell>
</template>
