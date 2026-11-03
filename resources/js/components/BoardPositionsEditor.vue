<script setup lang="ts">
// The board positions on the Member record (#918, ADR-0038 §3). Position is free text; a native
// <datalist> offers the positions already in use. Adding saves at once and keeps the state of the
// record form this sits inside.
import BoardPositionRow from '@/components/BoardPositionRow.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
    memberId: number;
    positions: { id: number; position: string; years: string | null }[];
    suggestions: string[];
}>();

const SUGGESTIONS_ID = 'board-position-suggestions';

const form = useForm({ position: '', years: '' });

const add = () => {
    form.post(route('members.board-positions.store', { member: props.memberId }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <datalist :id="SUGGESTIONS_ID">
        <option v-for="suggestion in suggestions" :key="suggestion" :value="suggestion" />
    </datalist>

    <ul v-if="positions.length" class="flex flex-col gap-3 text-sm">
        <BoardPositionRow
            v-for="position in positions"
            :key="position.id"
            :member-id="memberId"
            :position="position"
            :suggestions-id="SUGGESTIONS_ID"
        />
    </ul>
    <p v-else class="text-muted-foreground text-sm">{{ trans('records.board.none') }}</p>

    <div class="grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]">
        <div class="grid gap-2">
            <Label for="new_board_position">{{ trans('records.board.add') }}</Label>
            <Input id="new_board_position" v-model="form.position" :list="SUGGESTIONS_ID" @keydown.enter.prevent="add" />
            <InputError :message="form.errors.position" />
        </div>
        <div class="grid gap-2">
            <Label for="new_board_years">{{ trans('records.board.years') }}</Label>
            <Input id="new_board_years" v-model="form.years" @keydown.enter.prevent="add" />
            <InputError :message="form.errors.years" />
        </div>
        <Button type="button" variant="outline" :disabled="form.processing" @click="add">{{ trans('records.add') }}</Button>
    </div>
</template>
