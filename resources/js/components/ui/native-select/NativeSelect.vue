<script setup lang="ts">
// Added from the new-york-v4 registry (#735) and changed from it:
// - restyled to match Input: h-11, square corners, text-base, rom-slate focus ring;
// - Phosphor caret instead of Lucide, with pr-9 so the text stops short of it;
// - `class` sizes the wrapper (w-full by default, upstream is w-fit), not the <select>;
// - the value type is local (NativeSelectValue), not reka-ui's AcceptableValue.
import type { HTMLAttributes } from 'vue'
import type { NativeSelectValue } from '.'
import { PhCaretDown } from '@phosphor-icons/vue'
import { useVModel } from '@vueuse/core'
import { cn } from '@/lib/utils'

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
