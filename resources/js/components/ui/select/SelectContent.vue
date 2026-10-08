<script setup lang="ts">
// Customised (#748): the list caps at 24rem or the space radix measures on its open side,
// whichever is smaller, so it stays on a short screen.
import type { SelectContentEmits, SelectContentProps } from 'radix-vue'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import {
  SelectContent,

  SelectPortal,
  SelectViewport,
  useForwardPropsEmits,
} from 'radix-vue'
import { computed } from 'vue'
import { SelectScrollDownButton, SelectScrollUpButton } from '.'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<SelectContentProps & { class?: HTMLAttributes['class'] }>(),
  {
    position: 'popper',
  },
)
const emits = defineEmits<SelectContentEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SelectPortal>
    <SelectContent
      v-bind="{ ...forwarded, ...$attrs }" :class="cn(
        'relative z-50 max-h-[min(24rem,var(--radix-select-content-available-height))] min-w-32 overflow-hidden rounded-none border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
        position === 'popper'
          && 'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
        props.class,
      )
      "
    >
      <SelectScrollUpButton />
      <SelectViewport :class="cn('p-1', position === 'popper' && 'h-(--radix-select-trigger-height) w-full min-w-(--radix-select-trigger-width)')">
        <slot />
      </SelectViewport>
      <SelectScrollDownButton />
    </SelectContent>
  </SelectPortal>
</template>
