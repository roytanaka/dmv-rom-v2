<script setup lang="ts">
// PROTOTYPE (#405) — the one thing every variant shares: the number boxes themselves.
//
// Shared on purpose, and only this far. #404 fixed the fields (one integer for most
// Groups, two for the tour-leading ones) and fixed that they are required at the entry
// surface. What is *not* shared is the button beside them, the copy around them, or
// where on the page they sit — that is exactly what the four variants disagree about.
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GroupFixture } from './types';

const props = defineProps<{
    group: GroupFixture;
    /** Prefix so two variants on one page never collide on an id. */
    idPrefix: string;
    disabled?: boolean;
    /** Lay the boxes out side by side rather than stacked. */
    inline?: boolean;
}>();

const visitors = defineModel<string>('visitors', { required: true });
const extra = defineModel<string>('extra', { required: true });
</script>

<template>
    <div class="flex flex-wrap gap-3" :class="props.inline ? 'items-end' : 'flex-col sm:flex-row sm:items-end'">
        <div class="flex flex-col gap-1">
            <Label :for="`${idPrefix}-visitors`" class="text-xs">{{ group.visitorLabel }}</Label>
            <Input
                :id="`${idPrefix}-visitors`"
                v-model="visitors"
                type="number"
                inputmode="numeric"
                min="0"
                :disabled="disabled"
                class="w-28 tabular-nums"
                placeholder="0"
            />
        </div>
        <div v-if="group.collects === 'two'" class="flex flex-col gap-1">
            <Label :for="`${idPrefix}-extra`" class="text-xs">{{ group.extraLabel }}</Label>
            <Input
                :id="`${idPrefix}-extra`"
                v-model="extra"
                type="number"
                inputmode="numeric"
                min="0"
                :disabled="disabled"
                class="w-28 tabular-nums"
                placeholder="0"
            />
        </div>
    </div>
</template>
