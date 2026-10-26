<script setup lang="ts">
// A Group flag as a full-width row (#848): the label, an optional line under it (the records
// behind a capability, #844), and a Switch on the right. A guard error tints the row and lands
// under it, announced as an alert. The row's padding gives the Switch its touch height.
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

defineProps<{ id: string; label: string; note?: string | null; error?: string }>();
const checked = defineModel<boolean>({ required: true });
</script>

<template>
    <div :class="['flex flex-col gap-2 border p-3', error ? 'border-destructive bg-destructive-bg' : 'border-border']">
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <Label :for="id">{{ label }}</Label>
                <p v-if="note" class="text-muted-foreground text-sm">{{ note }}</p>
            </div>
            <Switch :id="id" :checked="checked" @update:checked="(on: boolean) => (checked = on)" />
        </div>
        <InputError :message="error" role="alert" />
    </div>
</template>
