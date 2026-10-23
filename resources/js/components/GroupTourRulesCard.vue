<script setup lang="ts">
// The Settings tab's Tour rules card (#793, ADR-0033 §7) — the Chair of a vetting Group sets the
// trainee Tour, the starter Tours and whether LOA removes qualifications. The status rules read
// these when a Member's standing changes. One PATCH; the server re-checks the TourPolicy.
// Tour names are content, shown as authored (ADR-0004).
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

export type SettingsTourRules = {
    traineeTourId: number | null;
    starterTourIds: number[];
    loaRemovesQualifications: boolean;
    tours: { id: number; name: string; active: boolean }[];
};

const props = defineProps<{
    rules: SettingsTourRules;
    groupSlug: string;
}>();

const form = useForm<{ trainee_tour_id: number | null; starter_tour_ids: number[]; loa_removes_qualifications: boolean }>({
    trainee_tour_id: props.rules.traineeTourId,
    starter_tour_ids: [...props.rules.starterTourIds],
    loa_removes_qualifications: props.rules.loaRemovesQualifications,
});

const toggleStarter = (id: number, on: boolean) => {
    form.starter_tour_ids = on ? [...form.starter_tour_ids, id] : form.starter_tour_ids.filter((tourId) => tourId !== id);
};

const save = () => form.patch(route('groups.tour-rules.update', { group: props.groupSlug }), { preserveScroll: true });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ trans('group.tour_rules.heading') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <Label for="trainee-tour">{{ trans('group.tour_rules.trainee_label') }}</Label>
                <NativeSelect id="trainee-tour" v-model="form.trainee_tour_id" class="sm:w-80">
                    <option :value="null">{{ trans('group.tour_rules.none') }}</option>
                    <option v-for="tour in rules.tours" :key="tour.id" :value="tour.id">{{ tour.name }}</option>
                </NativeSelect>
                <InputError :message="form.errors.trainee_tour_id" />
            </div>
            <fieldset class="flex flex-col gap-1.5">
                <legend class="mb-1.5 text-sm font-medium">{{ trans('group.tour_rules.starter_label') }}</legend>
                <p v-if="rules.tours.length === 0" class="text-muted-foreground text-sm">{{ trans('group.tour_rules.no_tours') }}</p>
                <label v-for="tour in rules.tours" :key="tour.id" class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                    <Checkbox :checked="form.starter_tour_ids.includes(tour.id)" @update:checked="(on: boolean) => toggleStarter(tour.id, on)" />
                    {{ tour.name }}
                </label>
                <InputError :message="form.errors.starter_tour_ids" />
            </fieldset>
            <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="form.loa_removes_qualifications" @update:checked="(on: boolean) => (form.loa_removes_qualifications = on)" />
                {{ trans('group.tour_rules.loa_label') }}
            </label>
            <div class="flex justify-end">
                <Button type="button" size="sm" :disabled="form.processing" @click="save">
                    {{ trans('group.scheduling_panel.save') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
