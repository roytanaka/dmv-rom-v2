<script setup lang="ts">
// The one time picker (#639, ADR-0028): an hour select and a minute select on the minute grid.
// Nobody schedules to the minute, so it offers only grid steps — twelve at the default five
// minutes, four at self-serve's fifteen. A native `<input type="time">` cannot do this: its
// `step` only validates, and the browser popup still lists all sixty minutes. ESLint bans the
// native time inputs so every picker comes through here.
//
// The value is the `HH:mm` the forms send, or '' until an hour is chosen. The outer <Label>
// targets `id`, which lands on the hour select; the minute select names itself.
import { NativeSelect } from '@/components/ui/native-select';
import { DEFAULT_STEP_MINUTES, joinTime, minuteOptions, splitTime } from '@/scheduling/timeGrid';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        required?: boolean;
        stepMinutes?: number;
        // An accessible name for the hour select when no outer <Label> targets it.
        hourLabel?: string;
    }>(),
    { stepMinutes: DEFAULT_STEP_MINUTES },
);

const model = defineModel<string>({ default: '' });

// Held locally so a minute picked before the hour is not lost while the value is still ''.
const hour = ref<number | null>(null);
const minute = ref<number | null>(null);

watch(
    model,
    (value) => {
        if (value === current()) {
            return;
        }
        ({ hour: hour.value, minute: minute.value } = splitTime(value));
    },
    { immediate: true },
);

function current(): string {
    return hour.value === null ? '' : joinTime(hour.value, minute.value ?? 0);
}

function pickHour(value: unknown) {
    hour.value = Number(value);
    minute.value ??= 0;
    model.value = current();
}

function pickMinute(value: unknown) {
    minute.value = Number(value);
    model.value = current();
}

// Hours read in the reader's locale ("9 a.m." / "9 h"); the value is always 0–23.
const locale = usePage<SharedData>().props.locale;
const hourFormat = new Intl.DateTimeFormat(locale, { hour: 'numeric', timeZone: 'UTC' });
const hours = Array.from({ length: 24 }, (_, value) => ({ value, label: hourFormat.format(Date.UTC(2000, 0, 1, value)) }));

const minutes = computed(() => minuteOptions(props.stepMinutes, minute.value));
</script>

<template>
    <div class="flex items-center gap-2">
        <NativeSelect
            :id="id"
            :model-value="hour ?? ''"
            :required="required"
            :aria-label="hourLabel"
            class="min-w-0 flex-1"
            @update:model-value="pickHour"
        >
            <option value="" disabled>–</option>
            <option v-for="option in hours" :key="option.value" :value="option.value">{{ option.label }}</option>
        </NativeSelect>
        <span aria-hidden="true">:</span>
        <NativeSelect
            :model-value="minute ?? ''"
            :required="required"
            :aria-label="trans('scheduling.time_field.minute')"
            class="w-auto shrink-0"
            @update:model-value="pickMinute"
        >
            <option value="" disabled>–</option>
            <option v-for="option in minutes" :key="option" :value="option">{{ String(option).padStart(2, '0') }}</option>
        </NativeSelect>
    </div>
</template>
