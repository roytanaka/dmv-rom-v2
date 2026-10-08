<script setup lang="ts">
// Customised (#739): the box caps at the viewport height and a phone's width less a 1rem gutter.
// Its body scrolls in an inner flex column (p-6, gap-4). Sticky fails inside a grid, so the column
// is flex. The Header and Footer offsets (-top-6, -mx-6, -mt-6 and so on) cancel this p-6 and half
// of gap-4: change them together.
import { cn } from '@/lib/utils';
import {
    AlertDialogContent,
    AlertDialogOverlay,
    AlertDialogPortal,
    useForwardPropsEmits,
    type AlertDialogContentEmits,
    type AlertDialogContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<AlertDialogContentProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<AlertDialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <AlertDialogPortal>
        <AlertDialogOverlay
            class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <AlertDialogContent
            v-bind="forwarded"
            :class="
                cn(
                    'fixed left-1/2 top-1/2 z-50 flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 flex-col border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-top-[48%] rounded-none',
                    props.class,
                )
            "
        >
            <div class="flex min-h-0 flex-col gap-4 overflow-y-auto p-6 *:shrink-0">
                <slot />
            </div>
        </AlertDialogContent>
    </AlertDialogPortal>
</template>
