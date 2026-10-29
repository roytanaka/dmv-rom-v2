<script setup lang="ts">
// A searchable list of Members to tick (#873), shared by the roster's Add member dialog and
// the Create sub-group dialog's Chair field (#885). The candidates arrive with the page; the
// search is a client-side substring of the full name. The ticked Members show as chips above
// the search, in the order they were picked, so a change to the search text keeps them.
// `multiple: false` makes a tick replace the pick (one Member at most). The caller renders
// the label, pointing it at `inputId`.
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { type RosterCandidate } from '@/types';
import { PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{ candidates: RosterCandidate[]; inputId: string; multiple?: boolean }>(), { multiple: true });
const selected = defineModel<number[]>({ required: true });

const search = ref('');

const candidateName = (candidate: RosterCandidate) => `${candidate.last_name}, ${candidate.first_name}`;

const selectedCandidates = computed(() =>
    selected.value
        .map((id) => props.candidates.find((candidate) => candidate.id === id))
        .filter((candidate): candidate is RosterCandidate => candidate !== undefined),
);

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') return props.candidates;
    return props.candidates.filter((candidate) => `${candidate.first_name} ${candidate.last_name}`.toLowerCase().includes(needle));
});

const toggle = (id: number, on: boolean) => {
    const rest = selected.value.filter((picked) => picked !== id);
    if (!on) selected.value = rest;
    else selected.value = props.multiple ? [...rest, id] : [id];
};
</script>

<template>
    <div v-if="selectedCandidates.length" class="flex flex-wrap gap-1.5">
        <Badge v-for="candidate in selectedCandidates" :key="candidate.id" variant="secondary" class="gap-1 font-normal">
            {{ candidateName(candidate) }}
            <button
                type="button"
                class="hover:text-foreground -mr-0.5 flex items-center justify-center rounded-full transition-colors pointer-coarse:size-11"
                :aria-label="trans('group.roster.add_remove_selected', { name: candidateName(candidate) })"
                @click="toggle(candidate.id, false)"
            >
                <PhX class="size-3" />
            </button>
        </Badge>
    </div>
    <Input :id="inputId" v-model="search" type="search" :placeholder="trans('group.roster.search.placeholder')" />
    <ul class="max-h-48 overflow-y-auto border">
        <li v-for="candidate in filtered" :key="candidate.id">
            <!-- A ticked row takes the heritage-blue wash, so it never reads as hover. -->
            <label
                class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-sm pointer-coarse:min-h-11"
                :class="selected.includes(candidate.id) ? 'bg-rom-slate-50 font-medium' : 'hover:bg-muted'"
            >
                <Checkbox :checked="selected.includes(candidate.id)" @update:checked="(on: boolean) => toggle(candidate.id, on)" />
                {{ candidateName(candidate) }}
            </label>
        </li>
        <li v-if="!filtered.length" class="text-muted-foreground px-3 py-2 text-sm">
            {{ trans('group.roster.no_candidates') }}
        </li>
    </ul>
</template>
