<script setup lang="ts">
// The Object picker (#586, ADR-0026 §3) — a searchable multi-select for the handling collection a
// Gallery Interpreter takes onto the floor. Shown on the three write flows (write my shift, take,
// place) only when the Group has active Objects; a Group with no Objects never renders it. Built
// on radix-vue's Combobox primitive (the repo's installed combobox lib), held open inline inside
// its dialog: a search box narrows the list, each row toggles, and the chosen Objects show as
// removable chips above. The server re-checks required, active, and the double-booking block on
// every write regardless of what this offers.
import { Badge } from '@/components/ui/badge';
import type { ObjectOption } from '@/types';
import { PhCheck, PhX } from '@phosphor-icons/vue';
import { ComboboxContent, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxRoot, ComboboxViewport } from 'radix-vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    options: ObjectOption[];
    // The picker's field labels, passed in so the same component reads correctly on every flow.
    searchPlaceholder: string;
    noMatches: string;
}>();

// The selected Object ids, two-way bound to the owning form field.
const model = defineModel<number[]>({ required: true });

// The search term drives the visible rows; radix's own filter is disabled below so the one shown
// list is this filter's, matched on the Object name the reader is scanning for.
const search = ref('');

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (needle === '') {
        return props.options;
    }

    return props.options.filter((option) => option.name.toLowerCase().includes(needle));
});

// The chosen Objects, in the Group's picker order, so the chips read stably as the search narrows.
const selected = computed(() => props.options.filter((option) => model.value.includes(option.id)));

const removeObject = (id: number) => {
    model.value = model.value.filter((selectedId) => selectedId !== id);
};
</script>

<template>
    <ComboboxRoot
        v-model="model"
        v-model:search-term="search"
        multiple
        :open="true"
        :filter-function="(value: number[]) => value"
        class="flex flex-col gap-2"
    >
        <!-- The chosen Objects, removable, so a long list is not re-scrolled to unpick one. -->
        <div v-if="selected.length" class="flex flex-wrap gap-1.5">
            <Badge v-for="option in selected" :key="option.id" variant="secondary" class="gap-1 font-normal">
                {{ option.name }}
                <button
                    type="button"
                    class="hover:text-destructive -mr-0.5 rounded-full transition-colors"
                    :aria-label="option.name"
                    @click="removeObject(option.id)"
                >
                    <PhX class="size-3" />
                </button>
            </Badge>
        </div>

        <ComboboxInput
            :placeholder="searchPlaceholder"
            class="border-input bg-background focus-visible:border-rom-slate focus-visible:ring-rom-slate-50 flex h-11 w-full rounded-none border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden"
        />

        <ComboboxContent position="inline" class="max-h-56 overflow-y-auto rounded-none border">
            <ComboboxViewport>
                <p v-if="!filtered.length" class="text-muted-foreground p-2 text-sm">{{ noMatches }}</p>
                <ComboboxItem
                    v-for="option in filtered"
                    :key="option.id"
                    :value="option.id"
                    class="data-[highlighted]:bg-muted flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm"
                >
                    <span>{{ option.name }}</span>
                    <ComboboxItemIndicator>
                        <PhCheck class="text-rom-slate size-4" />
                    </ComboboxItemIndicator>
                </ComboboxItem>
            </ComboboxViewport>
        </ComboboxContent>
    </ComboboxRoot>
</template>
