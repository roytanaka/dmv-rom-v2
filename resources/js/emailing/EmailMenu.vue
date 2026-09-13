<script setup lang="ts">
// The "Email ▾" control (#489, #490, ADR-0024 §6) — one control wherever an Audience is
// reachable: the Group page and roster, an opened Schedule, a Shift with a seat taken, and
// the Directory. Its menu *is* the Audience list: each item names an Audience the viewer may
// pick and its live count, fetched from the Audience index (§5), and a last item, "Pick
// people…", opens a hand-pick from the page's roster. Picking either opens the stepped
// composer sheet.
//
// Context-driven by props so the same control serves every surface, each passing its own
// context and roster. Whether "Pick people…" shows is the server's call too: it appears only
// when the index offered a hand-pick Audience, so a plain Member on the Directory — who may
// reach the three leadership Audiences but not hand-pick — sees none.
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import { emptyReasonKey, menuFromIndex, type AudienceOption, type EmailReason, type Recipient } from '@/emailing/composer';
import { PhCaretDown, PhEnvelopeSimple } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        context: string;
        contextSubject: string | null;
        groupName: string;
        roster: Recipient[];
        // Why the viewer can pick no Audience here (#513, ADR-0024 §6), from the page's props —
        // null when at least one is pickable. When set, the control greys (but stays clickable),
        // names the reason on hover, and opens to that reason as its one line; it never fetches.
        reason?: EmailReason | null;
    }>(),
    { reason: null },
);

const audiences = ref<AudienceOption[]>([]);
const canHandPick = ref(false);
const loaded = ref(false);
const sheetOpen = ref(false);
const selected = ref<AudienceOption | null>(null);

// Fetch the pickable Audiences the first time the menu opens — live counts, resolved on the
// server (§5). {@see menuFromIndex} splits the response into the named Audiences the menu
// lists and whether the actor may hand-pick; the menu renders its own always-last "Pick
// people…" item only then.
async function ensureLoaded(): Promise<void> {
    if (loaded.value) {
        return;
    }
    loaded.value = true;

    const query = new URLSearchParams({ context: props.context });
    if (props.contextSubject !== null) {
        query.set('subject', props.contextSubject);
    }

    const response = await fetch(`${route('audiences.index')}?${query.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return;
    }

    const data = (await response.json()) as { audiences: AudienceOption[] };
    const menu = menuFromIndex(data.audiences);
    audiences.value = menu.audiences;
    canHandPick.value = menu.canHandPick;
}

function pick(audience: AudienceOption | null): void {
    selected.value = audience;
    sheetOpen.value = true;
}
</script>

<template>
    <div>
        <DropdownMenu @update:open="(open: boolean) => open && !reason && ensureLoaded()">
            <!-- No pickable Audience here (#513): grey the button and name why on hover, but keep
                 it clickable — a real `disabled` swallows hover and dies on touch, so the phone
                 path is to open the menu to the same reason as its one greyed line. -->
            <Tooltip v-if="reason">
                <TooltipTrigger as-child>
                    <DropdownMenuTrigger as-child>
                        <Button type="button" variant="outline" size="sm" class="text-muted-foreground gap-1.5 opacity-60" aria-disabled="true">
                            <PhEnvelopeSimple class="size-4" />
                            {{ trans('broadcasts.composer.menu') }}
                            <PhCaretDown class="size-3" />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>{{ trans(emptyReasonKey(reason)) }}</TooltipContent>
            </Tooltip>
            <DropdownMenuTrigger v-else as-child>
                <Button type="button" variant="outline" size="sm" class="gap-1.5">
                    <PhEnvelopeSimple class="size-4" />
                    {{ trans('broadcasts.composer.menu') }}
                    <PhCaretDown class="size-3" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuItem v-if="reason" disabled>{{ trans(emptyReasonKey(reason)) }}</DropdownMenuItem>
                <template v-else>
                    <DropdownMenuItem v-for="audience in audiences" :key="`${audience.key}:${audience.parameter ?? ''}`" @select="pick(audience)">
                        <span class="flex-1">{{ audience.label }}</span>
                        <span class="text-muted-foreground">{{ audience.count }}</span>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="canHandPick && audiences.length" />
                    <DropdownMenuItem v-if="canHandPick" @select="pick(null)">{{ trans('audience.hand_picked') }}</DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <ComposerSheet
            v-model:open="sheetOpen"
            :context="context"
            :context-subject="contextSubject"
            :group-name="groupName"
            :roster="roster"
            :audience="selected"
        />
    </div>
</template>
