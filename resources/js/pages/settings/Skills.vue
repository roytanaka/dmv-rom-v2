<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import SkillsSelector from '@/components/SkillsSelector.vue';
import { Button } from '@/components/ui/button';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { computed } from 'vue';

interface CatalogCategory {
    id: number;
    name: string;
    skills: { id: number; name: string }[];
}

interface Props {
    catalog: CatalogCategory[];
    selected: number[];
    // Per-resource UI hint from MemberPolicy (ADR-0017). Drives whether the save
    // control renders enabled; the server enforces the action regardless.
    can: { update: boolean };
}

const props = defineProps<Props>();

const localizeHref = useLocalizedHref();

// `computed`, not a plain const: after a full-page locale switch the messages load
// async, so a `trans()` snapshot taken at setup captures the raw key before the
// locale is ready. A computed re-derives the label reactively once it resolves.
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.skills.title'),
        href: localizeHref('/settings/skills'),
    },
]);

const form = useForm<{ skills: number[] }>({
    skills: [...props.selected],
});

const submit = () => {
    form.patch(route('settings.skills.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="trans('settings.skills.title')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="trans('settings.skills.heading')" :description="trans('settings.skills.description')" />

                <form @submit.prevent="submit" class="space-y-6">
                    <p v-if="catalog.length === 0" class="text-sm text-neutral-600">{{ trans('settings.skills.empty') }}</p>

                    <SkillsSelector v-else v-model="form.skills" :catalog="catalog" />

                    <div class="flex items-center gap-4">
                        <Button :loading="form.processing" :disabled="!can.update">{{ trans('settings.skills.save') }}</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">{{ trans('settings.skills.saved') }}</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
