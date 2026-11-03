<script setup lang="ts">
// One board position on the Member record (#918, ADR-0038 §3). A field saves when it changes, as
// the Legacy app's did, and keeps the state of the record form around it.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
    memberId: number;
    position: { id: number; position: string; years: string | null };
    suggestionsId: string;
}>();

const stayOnPage = { preserveScroll: true, preserveState: true };
const params = { member: props.memberId, boardPosition: props.position.id };

const form = useForm({ position: props.position.position, years: props.position.years ?? '' });

const save = () => {
    form.patch(route('members.board-positions.update', params), stayOnPage);
};

const remove = () => {
    router.delete(route('members.board-positions.destroy', params), stayOnPage);
};
</script>

<template>
    <li class="grid items-start gap-2 sm:grid-cols-[1fr_1fr_auto]">
        <div class="grid gap-2">
            <Input
                v-model="form.position"
                :list="suggestionsId"
                :aria-label="trans('records.board.position')"
                @change="save"
                @keydown.enter.prevent
            />
            <InputError :message="form.errors.position" />
        </div>
        <div class="grid gap-2">
            <Input v-model="form.years" :aria-label="trans('records.board.years')" @change="save" @keydown.enter.prevent />
            <InputError :message="form.errors.years" />
        </div>
        <Button type="button" variant="ghost" size="sm" :aria-label="trans('records.board.remove', { position: position.position })" @click="remove">
            {{ trans('records.remove') }}
        </Button>
    </li>
</template>
