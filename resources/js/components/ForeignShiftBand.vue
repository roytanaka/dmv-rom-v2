<script setup lang="ts">
// A foreign open-Shift band (#361, ADR-0021 §Sign-up) — one owning Group's `open` Shifts a
// reader discovers on another Group's Schedule. The band is *always present* but collapsed
// to one line ("2 more open to you — Visitor Wayfinders"): nobody turns on a thing they do
// not know exists, so discovery is not hidden behind an off-by-default toggle. It is always
// attributed to its owning Group and never interleaved with that Group's own Shifts.
//
// Foreign Shifts carry no authoring affordances (the server sends `can.assign: false` and no
// seat `signup_id`), so only take / drop can fire from the reused ShiftCard; assign and
// remove are wired but never render.
import ShiftCard from '@/components/ShiftCard.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { type ForeignBand } from '@/scheduling/agenda';
import { type ForeignShiftItem } from '@/types';
import { PhCaretDown } from '@phosphor-icons/vue';
import { transChoice } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

const props = defineProps<{ band: ForeignBand<ForeignShiftItem>; expanded: boolean }>();

const emit = defineEmits<{
    take: [shift: ForeignShiftItem];
    drop: [shift: ForeignShiftItem];
}>();

// The master open/close-all sets every band's default state; a band stays individually
// toggleable after, so `open` tracks `expanded` but can then be overridden per band.
const open = ref(props.expanded);
watch(
    () => props.expanded,
    (value) => (open.value = value),
);
</script>

<template>
    <Collapsible v-model:open="open" class="group/band border-rom-ink/15 rounded-md border border-dashed">
        <CollapsibleTrigger
            class="text-muted-foreground hover:text-rom-ink flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition-colors"
        >
            <span>{{ transChoice('group.scheduling_panel.foreign.summary', band.shifts.length, { group: band.group }) }}</span>
            <PhCaretDown class="size-4 shrink-0 transition-transform group-data-[state=open]/band:rotate-180" />
        </CollapsibleTrigger>
        <CollapsibleContent>
            <div class="flex flex-col gap-2 px-3 pb-3">
                <ShiftCard v-for="shift in band.shifts" :key="shift.id" :shift="shift" @take="emit('take', shift)" @drop="emit('drop', shift)" />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
