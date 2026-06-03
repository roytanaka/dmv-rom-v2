<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    href: string;
    tabindex?: number;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-rom-slate decoration-rom-slate/40 hover:text-rom-slate-700 underline underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
    >
        <slot />
    </Link>
</template>
