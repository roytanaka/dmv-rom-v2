<script setup lang="ts">
// A Member's DMV-wide standing as a status chip (#169, PRD #167). Maps the
// `standing` value the MemberResource exposes (the Member's Category) to a Badge
// tone and a chrome label. Shared so the directory list and the profile page render
// the identical badge — the single home for the standing → tone/label mapping.
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ standing: string }>();

// Tone per standing. Anything unmapped falls back to the neutral chip, so a future
// Category never renders an untoned badge.
const TONES: Record<string, BadgeVariants['variant']> = {
    active: 'success',
    honourary: 'info',
    sustaining: 'secondary',
    loa: 'warning',
};

const variant = computed<BadgeVariants['variant']>(() => TONES[props.standing] ?? 'secondary');
const label = computed(() => trans(`member.standing.${props.standing}`));
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
