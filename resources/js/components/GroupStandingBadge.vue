<script setup lang="ts">
// A member's within-Group standing as a status chip (#189, PRD #186). Maps the
// `group_standing` value the roster exposes (App\Enums\MembershipStatus) to a Badge
// tone and a chrome label. Distinct from StandingBadge, which renders a Member's
// DMV-wide Category; standing is per-Group. Renders nothing for Full — the roster
// only flags a standing that differs from the norm.
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ standing: string }>();

// Tone per standing. Anything unmapped falls back to the neutral chip, so a future
// status never renders an untoned badge.
const TONES: Record<string, BadgeVariants['variant']> = {
    loa: 'warning',
    inactive: 'secondary',
    emeritus: 'info',
};

const variant = computed<BadgeVariants['variant']>(() => TONES[props.standing] ?? 'secondary');
const label = computed(() => trans(`group.standing.${props.standing}`));
</script>

<template>
    <Badge v-if="standing !== 'full'" :variant="variant">{{ label }}</Badge>
</template>
