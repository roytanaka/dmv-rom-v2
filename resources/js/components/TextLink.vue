<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    href: string;
    tabindex?: number;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    as?: string;
}

const props = defineProps<Props>();

// An in-page anchor (`#heading`) is a plain link, so the browser jumps to it
// without an Inertia visit back to the server.
const isAnchor = computed(() => props.href.startsWith('#'));

const linkClass =
    'text-rom-slate decoration-rom-slate/40 hover:text-rom-slate-700 underline underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!';
</script>

<template>
    <a v-if="isAnchor" :href="href" :tabindex="tabindex" :class="linkClass">
        <slot />
    </a>
    <Link v-else :href="href" :tabindex="tabindex" :method="method" :as="as" :class="linkClass">
        <slot />
    </Link>
</template>
