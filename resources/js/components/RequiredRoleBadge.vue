<script setup lang="ts">
// The Required-role badge (#518, PRD #516, ADR-0025 §6). Names the role a Help
// article's task needs — "Needs: Scheduler or Chair" — beside its title in the index
// and at the top of the article. It never hides the article; a Member can read ahead
// of a role. `requires` is the article's role tokens (a Role value, or a non-Group
// tier: `super_tier`, `support_operator`, `records`); an empty array shows no badge.
// Labels, prefix, and the "or"
// joiner are chrome (ADR-0004), resolved via trans() wrapped in computed so they
// survive the async message load and a full-page locale switch.
import { Badge } from '@/components/ui/badge';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ requires: string[] }>();

const label = computed(() => {
    const roles = props.requires.map((role) => trans(`help.required_role.role.${role}`));
    const joiner = ` ${trans('help.required_role.or')} `;

    return `${trans('help.required_role.prefix')} ${roles.join(joiner)}`;
});
</script>

<template>
    <Badge v-if="requires.length" variant="secondary">{{ label }}</Badge>
</template>
