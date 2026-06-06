<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { onMounted, ref } from 'vue';

const isOpen = ref(true);

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <!-- flex-col so the full-width top bar stacks above the [rail][content] row;
         --header-height (= TopBar h-16 / 4rem) offsets the rail's fixed positioning
         so it starts below the bar (consumed in Sidebar.vue). -->
    <SidebarProvider :default-open="isOpen" :open="isOpen" class="flex-col [--header-height:4rem]" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
