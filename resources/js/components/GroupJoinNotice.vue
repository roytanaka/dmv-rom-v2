<script setup lang="ts">
// The join notice (#869, ADR-0037 §2): what a non-member sees on a Group's Meetings or Tours tab,
// in place of the members-only content. One line and a link to the How to join card on the
// Overview, whose fixed `how-to-join` anchor the link lands on.
import TextLink from '@/components/TextLink.vue';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ groupSlug: string }>();

const localizeHref = useLocalizedHref();
const joinHref = computed(() => `${localizeHref(route('groups.show', { group: props.groupSlug }, false))}#how-to-join`);
</script>

<template>
    <div class="py-12 text-center">
        <p class="text-muted-foreground text-base">{{ trans('group.members_only') }}</p>
        <p class="mt-2 text-sm">
            <TextLink :href="joinHref">{{ trans('group.how_to_join') }}</TextLink>
        </p>
    </div>
</template>
