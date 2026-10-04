<script setup lang="ts">
// A date plus a grid time (#639, ADR-0028) — the replacement for `<input type="datetime-local">`,
// whose minute list cannot be narrowed. The value is the `YYYY-MM-DDTHH:mm` the forms send
// (org wall clock, read by `App\Support\OrgTime`), or '' until both halves are chosen. The
// outer <Label> targets `id`, which lands on the date input.
import TimeField from '@/components/TimeField.vue';
import { Input } from '@/components/ui/input';
import { DEFAULT_STEP_MINUTES } from '@/scheduling/timeGrid';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

withDefaults(defineProps<{ id?: string; required?: boolean; stepMinutes?: number }>(), { stepMinutes: DEFAULT_STEP_MINUTES });

const model = defineModel<string>({ default: '' });

// Held locally so one half chosen before the other is not lost while the value is still ''.
const date = ref('');
const time = ref('');

watch(
    model,
    (value) => {
        if (value === current()) {
            return;
        }
        date.value = value.slice(0, 10);
        time.value = value.slice(11, 16);
    },
    { immediate: true },
);

function current(): string {
    return date.value && time.value ? `${date.value}T${time.value}` : '';
}

watch([date, time], () => {
    model.value = current();
});
</script>

<template>
    <div class="grid gap-2 sm:grid-cols-2">
        <Input :id="id" v-model="date" type="date" :required="required" />
        <TimeField v-model="time" :required="required" :step-minutes="stepMinutes" :hour-label="trans('scheduling.time_field.hour')" />
    </div>
</template>
