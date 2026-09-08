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
import { PhDetective } from '@phosphor-icons/vue';
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
        class="fixed right-4 bottom-4 z-50 flex items-center gap-2 print:hidden"
        :class="active ? 'rounded-sm border border-black bg-white px-2 py-1.5 shadow-lg' : ''"
    >
        <span v-if="active" class="text-rom-ink flex items-center gap-1.5 text-sm font-medium whitespace-nowrap">
            <PhDetective class="h-5 w-5 shrink-0" />
            {{ active.as.name }}
            <span v-if="active.as.descriptor" class="text-muted-foreground font-normal">· {{ active.as.descriptor }}</span>
        </span>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    :aria-label="active ? undefined : 'Impersonate (dev)'"
                    :title="active ? undefined : 'Impersonate (dev)'"
                    class="shrink-0 transition-colors"
                    :class="
                        active
                            ? 'text-rom-ink rounded-sm px-2 py-1 text-sm font-medium whitespace-nowrap hover:bg-neutral-300'
                            : 'text-rom-ink flex size-10 items-center justify-center rounded-sm border border-black bg-white shadow-lg hover:bg-neutral-300'
                    "
                >
                    <template v-if="active">Switch ▾</template>
                    <PhDetective v-else class="h-5 w-5" />
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
            class="shrink-0 rounded-sm px-2 py-1 text-sm font-medium whitespace-nowrap text-red-600 transition-colors hover:bg-neutral-300"
            @click="stop"
        >
            Stop
        </button>
    </div>
</template>
