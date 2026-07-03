<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

// Reusable Skills multi-select (#246, PRD #243). Renders the active catalog as
// category-headed groups of checkboxes and edits a flat set of selected skill ids
// through v-model. Deliberately decoupled from Settings so the future renewal flow
// can embed the same component — it knows only about a catalog and a selection,
// nothing about which page hosts it.

interface CatalogSkill {
    id: number;
    name: string;
}

interface CatalogCategory {
    id: number;
    name: string;
    skills: CatalogSkill[];
}

const props = defineProps<{
    catalog: CatalogCategory[];
    // The currently-selected skill ids. Flat multi-select — no per-skill state.
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const isChecked = (id: number) => props.modelValue.includes(id);

const toggle = (id: number, checked: boolean) => {
    const next = checked ? [...props.modelValue, id] : props.modelValue.filter((selected) => selected !== id);

    emit('update:modelValue', next);
};
</script>

<template>
    <div class="space-y-8">
        <fieldset v-for="category in catalog" :key="category.id" class="space-y-3">
            <legend class="text-base font-semibold text-neutral-900">{{ category.name }}</legend>

            <div class="space-y-2">
                <div v-for="skill in category.skills" :key="skill.id" class="flex items-center gap-3">
                    <Checkbox
                        :id="`skill-${skill.id}`"
                        :checked="isChecked(skill.id)"
                        @update:checked="(checked: boolean) => toggle(skill.id, checked)"
                    />
                    <Label :for="`skill-${skill.id}`" class="font-normal">{{ skill.name }}</Label>
                </div>
            </div>
        </fieldset>
    </div>
</template>
