<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    defaultValue?: string | number;
    modelValue?: string | number;
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="modelValue"
        :class="
            cn(
                'flex min-h-20 w-full rounded-none border border-input bg-background px-3 py-2 text-base placeholder:text-muted-foreground focus-visible:border-rom-slate focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-rom-slate-50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    />
</template>
