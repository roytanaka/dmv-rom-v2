<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- text-foreground: the sidebar wrapper paints everything text-sidebar-foreground
         (near-white for the charcoal rail); the content pane opts back into ink text. -->
    <SidebarInset :class="['text-foreground', $props.class]">
        <slot />
    </SidebarInset>
</template>
