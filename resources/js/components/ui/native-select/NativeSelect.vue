<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { PhCaretDown } from '@phosphor-icons/vue'
import { useVModel } from '@vueuse/core'
import { cn } from '@/lib/utils'

// The registry types this as reka-ui's AcceptableValue; spelled out here so the
// component needs no direct reka-ui dependency.
type NativeSelectValue = string | number | bigint | Record<string, any> | null

defineOptions({
  inheritAttrs: false,
})

// `class` sizes the wrapper (the select fills it); everything else in $attrs —
// id, required, disabled, aria-label — lands on the <select>.
const props = defineProps<{ modelValue?: NativeSelectValue, class?: HTMLAttributes['class'] }>()

const emit = defineEmits<{
  'update:modelValue': [value: NativeSelectValue]
}>()

const modelValue = useVModel(props, 'modelValue', emit, {
  passive: true,
  defaultValue: '',
})
</script>

<template>
  <div
    :class="cn('group/native-select relative w-full has-[select:disabled]:opacity-50', props.class)"
    data-slot="native-select-wrapper"
  >
    <select
      v-bind="$attrs"
      v-model="modelValue"
      data-slot="native-select"
      class="flex h-11 w-full min-w-0 appearance-none truncate rounded-none border border-input bg-background py-2 pr-9 pl-3 text-base focus-visible:border-rom-slate focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-rom-slate-50 aria-invalid:border-destructive disabled:cursor-not-allowed"
    >
      <slot />
    </select>
    <PhCaretDown class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 opacity-50 select-none" aria-hidden="true" data-slot="native-select-icon" />
  </div>
</template>
