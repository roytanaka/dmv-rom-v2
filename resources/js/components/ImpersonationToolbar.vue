<script setup lang="ts">
// The dev/QA role-switcher — one stateful floating toolbar (Vercel-toolbar style) that
// renders purely from the `impersonation` shared prop and posts to the become/stop
// endpoints (#223, PRD #220, ADR-0009 dev half). Presentation only: it holds no
// authority of its own — the server prop and the route's environment gate are the
// security boundary. When the prop is null (production, or an ordinary Member) it does
// not render at all.
//
// One component, two states:
//   Idle   (super-tier, not impersonating) — a small "🛠 DEV · Impersonate ▾" pill that
//          opens a grouped picker; picking a Persona posts to `start` (become).
//   Active (impersonating) — a loud alert-coloured bar "⚠ IMPERSONATING {persona} · as
//          {operator}" with [Switch ▾] (reopens the picker → rebases) and [Stop]
//          (returns to the operator). Visibility keys off `active` in the prop, not the
//          current user's tier, so it persists even as a no-authority Persona.
//
// English-only, unlike the volunteer-facing chrome — this is a dev tool (ADR-0009), and
// the become/stop routes are non-localized to match.
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

// Become the chosen Persona (idle pick) or rebase onto it (active Switch) — the server
// resolves start-vs-rebase from the session, so both paths post the same `start`.
const become = (email: string) => {
    router.post(route('impersonation.start'), { email }, { preserveScroll: true });
};

// Return to the operator the impersonation started from.
const stop = () => {
    router.delete(route('impersonation.stop'), { preserveScroll: true });
};
</script>

<template>
    <!-- Renders nothing unless the server shipped the prop (non-prod AND super-tier or an
         active session). The prop's absence, not any client check, is the gate. -->
    <div
        v-if="impersonation"
        class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3"
        :class="active ? 'border-2 border-red-600 bg-red-50 px-4 py-2 text-red-900 shadow-lg' : ''"
    >
        <!-- Active: the loud alert bar. Persona name + standing, then the operator to
             return to. -->
        <span v-if="active" class="text-sm font-semibold whitespace-nowrap">
            ⚠ IMPERSONATING {{ active.as.name }}
            <span v-if="active.as.descriptor" class="font-normal">· {{ active.as.descriptor }}</span>
            · as {{ active.operator }}
        </span>

        <!-- The picker — shared by the idle pill and the active Switch. The trigger label
             differs by state; the grouped Persona list is identical, and comes entirely
             from the prop. -->
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

        <!-- Stop returns to the operator; requires only an active session server-side, so
             a no-authority Persona is never stranded. -->
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
