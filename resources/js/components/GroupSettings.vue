<script setup lang="ts">
// Group Settings tab (#604, ADR-0027) — one page of cards holding the Group's settings: values
// an officer sets once and the app reads on every later action. Records (Shifts, Meetings,
// roster lines) stay inline on the section where they are read (§2). The tab renders only to a
// viewer holding a configuration right; each card renders only behind its own `can` hint, and
// a scheduling card only while the Group runs scheduling. The server re-checks every save.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    // Whether the Group runs scheduling — the scheduling cards render only while it does.
    runsScheduling: boolean;
    // The Group's Reminder settings (#486), resolved only with `canManageReminders`.
    reminders: { enabled: boolean; leadDays: number } | null;
    canManageReminders: boolean;
    groupSlug: string;
}>();

const showReminders = computed(() => props.canManageReminders && props.runsScheduling && props.reminders !== null);

// --- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and lead
// days, gated by `canManageReminders`. One PATCH to the dedicated endpoint; the server
// re-checks the gate. The form seeds from the Group's current settings.
const reminderForm = useForm<{ reminders_enabled: boolean; reminder_lead_days: number }>({
    reminders_enabled: props.reminders?.enabled ?? false,
    reminder_lead_days: props.reminders?.leadDays ?? 0,
});

const saveReminders = () => reminderForm.patch(route('groups.reminders.update', { group: props.groupSlug }), { preserveScroll: true });
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and
             lead days for this Group's shift Reminders. Shown only to a Scheduler / Chair
             (`canManageReminders`) of a scheduling Group; the server re-checks on save. -->
        <Card v-if="showReminders">
            <CardHeader>
                <CardTitle>{{ trans('group.scheduling_panel.reminders.heading') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p class="text-muted-foreground text-sm">{{ trans('group.scheduling_panel.reminders.description') }}</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :checked="reminderForm.reminders_enabled" @update:checked="(on: boolean) => (reminderForm.reminders_enabled = on)" />
                    {{ trans('group.scheduling_panel.reminders.enabled_label') }}
                </label>
                <div class="flex flex-col gap-1.5">
                    <Label for="reminder-lead-days">{{ trans('group.scheduling_panel.reminders.lead_days_label') }}</Label>
                    <Input id="reminder-lead-days" v-model.number="reminderForm.reminder_lead_days" type="number" min="1" max="90" class="w-24" />
                    <InputError :message="reminderForm.errors.reminder_lead_days" />
                </div>
                <div class="flex justify-end">
                    <Button type="button" size="sm" :disabled="reminderForm.processing" @click="saveReminders">
                        {{ trans('group.scheduling_panel.save') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- The tab appears with authority, not with data (ADR-0027 §1): a viewer whose rights
             reach no card here still sees the tab, and this line instead of a blank page. -->
        <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.settings_empty') }}</p>
    </div>
</template>
