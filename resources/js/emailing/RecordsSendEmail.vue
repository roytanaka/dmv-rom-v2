<script setup lang="ts">
// Send email on a Records office page (#925, ADR-0038 §5, §7): a Member list or a Saved query run.
// The context offers one Audience, the whole list or the run, so the button opens the composer on
// it directly; there is no menu. The server names it and resolves it (ADR-0024 §5). A query run
// passes its unchecked rows as `exclude`, which the composer posts as removals; the browser never
// posts an address list.
import { Button } from '@/components/ui/button';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import { type AudienceOption } from '@/emailing/composer';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhEnvelopeSimple } from '@phosphor-icons/vue';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        context: 'member_list' | 'saved_query';
        contextSubject: string;
        label: string;
        exclude?: number[];
        disabled?: boolean;
    }>(),
    { exclude: () => [], disabled: false },
);

const page = usePage<SharedData>();
const audience = ref<AudienceOption | null>(null);
const sheetOpen = ref(false);
const loading = ref(false);

async function open(): Promise<void> {
    if (loading.value) {
        return;
    }
    loading.value = true;

    try {
        const query = new URLSearchParams({ context: props.context, subject: props.contextSubject });
        const response = await fetch(`${route('audiences.index')}?${query.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as { audiences: AudienceOption[] };
        audience.value = data.audiences[0] ?? null;
        sheetOpen.value = audience.value !== null;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <Button variant="outline" :disabled="disabled || loading" @click="open">
            <PhEnvelopeSimple aria-hidden="true" />
            {{ label }}
        </Button>

        <ComposerSheet
            v-if="audience"
            v-model:open="sheetOpen"
            :context="context"
            :context-subject="contextSubject"
            :group-name="page.props.name"
            :roster="[]"
            :audience="audience"
            :exclude="exclude"
        />
    </div>
</template>
