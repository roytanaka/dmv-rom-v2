<script setup lang="ts">
// Customised (#748): the menu caps at the space radix measures on its open side and scrolls.
// A caller's max-h-* replaces this cap, so a caller that wants a shorter menu writes
// max-h-[min(20rem,var(--radix-dropdown-menu-content-available-height))], not max-h-80.
import { cn } from '@/lib/utils';
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    useForwardPropsEmits,
    type DropdownMenuContentEmits,
    type DropdownMenuContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = withDefaults(defineProps<DropdownMenuContentProps & { class?: HTMLAttributes['class'] }>(), {
    sideOffset: 4,
});
const emits = defineEmits<DropdownMenuContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DropdownMenuPortal>
        <DropdownMenuContent
            v-bind="forwarded"
            :class="
                cn(
                    'z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-32 overflow-x-hidden overflow-y-auto rounded-none border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    props.class,
                )
            "
        >
            <slot />
        </DropdownMenuContent>
    </DropdownMenuPortal>
</template>
