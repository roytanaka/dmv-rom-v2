export { default as NativeSelect } from './NativeSelect.vue'
export { default as NativeSelectOptGroup } from './NativeSelectOptGroup.vue'
export { default as NativeSelectOption } from './NativeSelectOption.vue'

// What a NativeSelect's v-model holds: an option's bound `:value`.
export type NativeSelectValue = string | number | bigint | Record<string, any> | null
