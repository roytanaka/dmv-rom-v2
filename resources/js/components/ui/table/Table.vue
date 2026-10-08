<script setup lang="ts">
// ROM-tuned shadcn-vue Table. The ROM listing identity is baked into the
// defaults across the upstream parts (Table / TableHeader / TableRow /
// TableHead / TableCell) — class strings restyled in place, structure
// untouched, so registry updates stay mergeable. Here on the <table>: the 2px
// black top rule (`border-t-2 border-primary`, --primary is pure black) and
// 18px row text (`text-base`); TableHead overrides to the 13px-class uppercase
// heads. See docs/conventions.md § Styling and the design system.
//
// The table scrolls inside its own box, so a wide table never pushes the page
// sideways; on paper the box lets go so a printed report is never clipped.
// `pinFirstColumn` (#749) is for data grids (docs/conventions.md § Tables on
// small screens): the first cell of every row sticks to the left while the
// other columns scroll under it, and a mask fades the edge that has more
// columns, only while there is more to scroll to. The pinned cells paint
// `bg-card`, the surface the hours grids sit on, so columns slide behind them.
import { cn } from '@/lib/utils';
import { useResizeObserver, useScroll } from '@vueuse/core';
import { computed, ref, type HTMLAttributes } from 'vue';
import { fadeMask } from './fadeMask';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    pinFirstColumn?: boolean;
}>();

const scroller = ref<HTMLElement | null>(null);
const table = ref<HTMLTableElement | null>(null);

// `useScroll` measures on mount and on scroll; a resize (rotation, a font
// loading, a wider month label) changes the overflow without a scroll, so
// re-measure then too, along with the pinned column's width.
const { arrivedState, measure } = useScroll(scroller);
const inset = ref(0);
useResizeObserver([scroller, table], () => {
    measure();
    inset.value = table.value?.rows[0]?.cells[0]?.offsetWidth ?? 0;
});

const mask = computed(() =>
    props.pinFirstColumn ? fadeMask({ start: !arrivedState.left, end: !arrivedState.right, inset: inset.value }) : undefined,
);
</script>

<template>
    <div
        ref="scroller"
        class="relative w-full overflow-auto print:overflow-visible"
        :class="pinFirstColumn && '[-webkit-mask-image:var(--fade-mask)] [mask-image:var(--fade-mask)] print:[-webkit-mask-image:none] print:[mask-image:none]'"
        :style="mask ? { '--fade-mask': mask } : undefined"
    >
        <table
            ref="table"
            :class="
                cn(
                    'w-full caption-bottom border-t-2 border-primary text-base',
                    pinFirstColumn && '[&_tr>:first-child]:sticky [&_tr>:first-child]:left-0 [&_tr>:first-child]:z-10 [&_tr>:first-child]:bg-card',
                    props.class,
                )
            "
        >
            <slot />
        </table>
    </div>
</template>
