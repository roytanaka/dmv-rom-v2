<script setup lang="ts">
// PROTOTYPE (#467) — the post-send line: "queued for N Members, M could not be reached".
// Officers see the skipped names (#466 item 4); a Direct-message sender sees only the refusal.
import { Button } from '@/components/ui/button';
import { PhCheckCircle, PhWarningCircle } from '@phosphor-icons/vue';
import { fullName, type SendResult } from './model';

defineProps<{ result: SendResult; direct?: boolean; audienceLabel: string }>();
defineEmits<{ done: [] }>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <template v-if="direct && result.sent === 0">
            <div class="flex items-start gap-2 text-base">
                <PhWarningCircle class="text-destructive mt-0.5 size-5 shrink-0" />
                <p>
                    <strong>{{ fullName(result.skipped[0]) }} cannot be reached.</strong> Their email is switched off. Nothing was sent.
                </p>
            </div>
        </template>
        <template v-else>
            <div class="flex items-start gap-2 text-base">
                <PhCheckCircle class="mt-0.5 size-5 shrink-0 text-emerald-700" />
                <p>
                    <strong>Queued for {{ result.sent }} {{ result.sent === 1 ? 'Member' : 'Members' }}.</strong>
                    <span v-if="!direct"> Audience: {{ audienceLabel }}.</span>
                    Delivery takes up to an hour; you get a copy when it is done.
                </p>
            </div>
            <div v-if="result.skipped.length" class="flex items-start gap-2 text-sm">
                <PhWarningCircle class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                <p>
                    <strong>{{ result.skipped.length }} could not be reached</strong> (email switched off):
                    {{ result.skipped.map(fullName).join(', ') }}.
                </p>
            </div>
        </template>
        <div><Button type="button" @click="$emit('done')">Done</Button></div>
    </div>
</template>
