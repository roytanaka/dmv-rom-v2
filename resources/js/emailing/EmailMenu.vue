<script setup lang="ts">
// The "Email ▾" control (#489, ADR-0024 §6) — one control wherever an Audience is reachable.
// Its menu *is* the Audience list: each item names an Audience the viewer may pick and its
// live count, fetched from the Audience index (§5), and a last item, "Pick people…", opens a
// hand-pick from the page's roster. Picking either opens the stepped composer sheet.
//
// Context-driven by props so the same control serves the Group page now and the roster,
// Schedule, Shift, and Directory surfaces next, each passing its own context and roster.
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import { type AudienceOption, type Recipient } from '@/emailing/composer';
import { PhCaretDown, PhEnvelopeSimple } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{
    context: string;
    contextSubject: string | null;
    groupName: string;
    roster: Recipient[];
}>();

const audiences = ref<AudienceOption[]>([]);
const loaded = ref(false);
const sheetOpen = ref(false);
const selected = ref<AudienceOption | null>(null);

// Fetch the pickable Audiences the first time the menu opens — live counts, resolved on the
// server (§5). The hand-pick key, if the index returns it, is dropped: the menu renders its
// own "Pick people…" item so it is always present and always last.
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
    audiences.value = data.audiences.filter((audience) => audience.key !== 'hand_picked');
}

function pick(audience: AudienceOption | null): void {
    selected.value = audience;
    sheetOpen.value = true;
}
</script>

<template>
    <div>
        <DropdownMenu @update:open="(open: boolean) => open && ensureLoaded()">
            <DropdownMenuTrigger as-child>
                <Button type="button" variant="outline" size="sm" class="gap-1.5">
                    <PhEnvelopeSimple class="size-4" />
                    {{ trans('broadcasts.composer.menu') }}
                    <PhCaretDown class="size-3" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuItem v-for="audience in audiences" :key="`${audience.key}:${audience.parameter ?? ''}`" @select="pick(audience)">
                    <span class="flex-1">{{ audience.label }}</span>
                    <span class="text-muted-foreground">{{ audience.count }}</span>
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="audiences.length" />
                <DropdownMenuItem @select="pick(null)">{{ trans('audience.hand_picked') }}</DropdownMenuItem>
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
