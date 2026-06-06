<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- text-foreground: the sidebar wrapper paints everything text-sidebar-foreground
         (near-white for the charcoal rail); the content pane opts back into ink text.
         min-w-0: let this flex item shrink below its content's intrinsic width so the
         top bar's section scroll-strip scrolls internally instead of widening the page. -->
    <SidebarInset :class="['text-foreground min-w-0', $props.class]">
        <slot />
    </SidebarInset>
</template>
