<script setup lang="ts">
// PROTOTYPE — the section's list state. See #330.
//
// Shared across all three variants on purpose: #328 already decided the landing
// behaviour (list, then open; a single current published Schedule opens directly), so
// the list is not what this ticket is asking about. It is here only so the variants
// are judged with the thing that sits in front of them.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PhPlus } from '@phosphor-icons/vue';
import type { Dataset, Viewer } from './types';

defineProps<{ dataset: Dataset; viewer: Viewer }>();
defineEmits<{ open: [] }>();

const fmt = (from: string, to: string) => {
    const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
    return `${new Intl.DateTimeFormat('en-CA', opts).format(new Date(`${from}T00:00`))} – ${new Intl.DateTimeFormat('en-CA', opts).format(new Date(`${to}T00:00`))}`;
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-rom-ink text-2xl font-semibold">Scheduling</h2>
            <Button v-if="viewer.role === 'scheduler'" size="sm" class="gap-1.5"><PhPlus class="size-4" /> New schedule</Button>
        </div>

        <ul class="flex flex-col">
            <li v-for="entry in dataset.index" :key="entry.id" class="border-input border-b">
                <button
                    type="button"
                    class="hover:bg-muted/50 flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-1 py-3 text-left"
                    @click="$emit('open')"
                >
                    <span class="text-rom-ink min-w-40 flex-1 font-medium">{{ entry.name }}</span>
                    <Badge v-if="entry.state === 'draft'" variant="warning">Draft</Badge>
                    <span class="text-muted-foreground text-sm">{{ fmt(entry.startsOn, entry.endsOn) }}</span>
                    <span class="text-muted-foreground w-40 text-sm">
                        {{ entry.shiftCount }} shifts<template v-if="entry.openSlots"> · {{ entry.openSlots }} open</template>
                    </span>
                </button>
            </li>
        </ul>

        <p v-if="!dataset.index.length" class="text-muted-foreground py-12 text-center">{{ dataset.groupName }} hasn't published a schedule yet.</p>
    </div>
</template>
