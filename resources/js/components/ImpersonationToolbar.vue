<script setup lang="ts">
// Dev/QA role-switcher (ADR-0009). Presentation-only — the server prop and route
// environment gate are the security boundary; this component holds no authority.
// English-only intentionally: the become/stop routes are non-localized to match.
//
// Active-state visibility keys off `impersonation.active` (the prop), not the
// current user's tier — a no-authority Persona still sees Switch and Stop.
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const impersonation = computed(() => page.props.impersonation);
const active = computed(() => impersonation.value?.active ?? null);

// Idle pick and active Switch both post to `start`; the server resolves
// start-vs-rebase from the session.
const become = (email: string) => {
    router.post(route('impersonation.start'), { email }, { preserveScroll: true });
};

const stop = () => {
    router.delete(route('impersonation.stop'), { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="impersonation"
        class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3"
        :class="active ? 'border-2 border-red-600 bg-red-50 px-4 py-2 text-red-900 shadow-lg' : ''"
    >
        <span v-if="active" class="text-sm font-semibold whitespace-nowrap">
            ⚠ IMPERSONATING {{ active.as.name }}
            <span v-if="active.as.descriptor" class="font-normal">· {{ active.as.descriptor }}</span>
            · as {{ active.operator }}
        </span>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="shrink-0 border text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        active
                            ? 'border-red-600 px-2 py-1 text-red-900 hover:bg-red-100'
                            : 'border-rom-ink/20 bg-rom-ink hover:bg-rom-ink/90 px-3 py-1.5 text-white shadow-lg'
                    "
                >
                    {{ active ? 'Switch ▾' : '🛠 DEV · Impersonate ▾' }}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="center" :side-offset="8" class="max-h-96 w-72 overflow-y-auto">
                <template v-for="(group, index) in impersonation.personas" :key="group.key">
                    <DropdownMenuSeparator v-if="index > 0" />
                    <DropdownMenuLabel class="text-muted-foreground text-xs">{{ group.label }}</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="persona in group.personas"
                        :key="persona.email"
                        class="flex-col items-start gap-0"
                        @select="become(persona.email)"
                    >
                        <span class="font-medium">{{ persona.name }}</span>
                        <span class="text-muted-foreground text-xs">{{ persona.descriptor }}</span>
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <button
            v-if="active"
            type="button"
            class="shrink-0 border border-red-600 bg-red-600 px-2 py-1 text-sm font-medium whitespace-nowrap text-white transition-colors hover:bg-red-700"
            @click="stop"
        >
            Stop
        </button>
    </div>
</template>
