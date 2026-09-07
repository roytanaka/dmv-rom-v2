<script setup lang="ts">
// PROTOTYPE (#467) — the one entry point a host page mounts. It renders whichever
// affordance the active variant calls for, and opens that variant's composer:
//   A · a "Send email" button → ComposeA (dialog)
//   B · an "Email ▾" menu naming the Audiences → ComposeB (sheet, pre-filled)
//   C · an "Email" button plus a floating "N selected" bar → ComposeC (full page, seeded)
// Host pages own the tick boxes Variant C needs and pass the ticked ids in `selected`.
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { PhCaretDown, PhEnvelopeSimple } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';
import ComposeA from './ComposeA.vue';
import ComposeB from './ComposeB.vue';
import ComposeC from './ComposeC.vue';
import type { ComposeContext } from './model';
import { useVariant } from './variant';

const props = withDefaults(
    defineProps<{
        context: ComposeContext;
        selected?: number[];
        size?: 'default' | 'sm';
        variantStyle?: 'default' | 'secondary' | 'outline' | 'ghost';
        // Where the floating selection bar should not render (a nested entry point).
        noBar?: boolean;
    }>(),
    { selected: () => [], size: 'sm', variantStyle: 'outline', noBar: false },
);
const emit = defineEmits<{ clear: [] }>();

const { variant } = useVariant();
const openA = ref(false);
const openB = ref(false);
const openC = ref(false);
const audienceB = ref<string | null>(null);

const label = computed(() => (props.context.fixed ? `Message ${props.context.fixed.first_name}` : 'Send email'));

const openWith = (key: string | null) => {
    audienceB.value = key;
    openB.value = true;
};
</script>

<template>
    <span class="inline-flex items-center">
        <!-- A · one button -->
        <template v-if="variant === 'A'">
            <Button type="button" :variant="variantStyle" :size="size" class="gap-1.5" @click="openA = true">
                <PhEnvelopeSimple class="size-4" /> {{ label }}
            </Button>
            <ComposeA v-model:open="openA" :context="context" />
        </template>

        <!-- B · the Audiences as a menu -->
        <template v-else-if="variant === 'B'">
            <Button v-if="context.fixed" type="button" :variant="variantStyle" :size="size" class="gap-1.5" @click="openWith(null)">
                <PhEnvelopeSimple class="size-4" /> {{ label }}
            </Button>
            <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                    <Button type="button" :variant="variantStyle" :size="size" class="gap-1.5">
                        <PhEnvelopeSimple class="size-4" /> Email <PhCaretDown class="size-3.5 opacity-70" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64">
                    <DropdownMenuItem v-for="a in context.audiences" :key="a.key" class="flex justify-between gap-3" @select="openWith(a.key)">
                        <span>
                            {{ a.name }}
                            <span v-if="a.note" class="text-muted-foreground block text-xs">{{ a.note }}</span>
                        </span>
                        <span class="text-muted-foreground tabular-nums">{{ a.members.length }}</span>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="context.audiences.length && context.roster.length" />
                    <DropdownMenuItem v-if="context.roster.length" @select="openWith(null)">Pick people…</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <ComposeB v-model:open="openB" :context="context" :audience="audienceB" />
        </template>

        <!-- C · select first; the button is the fallback for "nobody ticked yet" -->
        <template v-else>
            <Button type="button" :variant="variantStyle" :size="size" class="gap-1.5" @click="openC = true">
                <PhEnvelopeSimple class="size-4" /> {{ context.fixed ? label : selected.length ? `Email ${selected.length} selected` : 'Email…' }}
            </Button>
            <Teleport v-if="!noBar && selected.length && !context.fixed" to="body">
                <div class="bg-rom-ink fixed bottom-16 left-1/2 z-40 flex -translate-x-1/2 items-center gap-3 px-4 py-2 text-sm text-white shadow-lg">
                    <span
                        ><strong>{{ selected.length }}</strong> selected</span
                    >
                    <Button type="button" size="sm" variant="secondary" class="gap-1.5" @click="openC = true"
                        ><PhEnvelopeSimple class="size-4" /> Email</Button
                    >
                    <button type="button" class="text-white/70 underline-offset-4 hover:underline" @click="emit('clear')">Clear</button>
                </div>
            </Teleport>
            <ComposeC v-model:open="openC" :context="context" :seed="selected" @sent="emit('clear')" />
        </template>
    </span>
</template>
