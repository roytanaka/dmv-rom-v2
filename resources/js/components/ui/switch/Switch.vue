<script setup lang="ts">
// shadcn-vue Switch (#848), retargeted from the CLI's reka-ui output to radix-vue, the headless
// library the rest of `ui/` uses (same SwitchRoot / SwitchThumb API). Props are delegated the way
// Checkbox.vue does it. On a touch screen the 24px track gets an invisible 44px tap area through
// `pointer-coarse:after:` (ADR-0031 §6), as the Checkbox does. The track and thumb keep their
// pill and circle: a switch is read by that shape, and the circle is the documented exception to
// square-by-default (docs/conventions.md § Styling and the design system).
import { cn } from '@/lib/utils';
import type { SwitchRootEmits, SwitchRootProps } from 'radix-vue';
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<SwitchRootEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <SwitchRoot
        v-bind="forwarded"
        :class="
            cn(
                'peer relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors pointer-coarse:after:absolute pointer-coarse:after:-inset-[10px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
                props.class,
            )
        "
    >
        <SwitchThumb
            :class="
                cn(
                    'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5',
                )
            "
        >
            <slot name="thumb" />
        </SwitchThumb>
    </SwitchRoot>
</template>
