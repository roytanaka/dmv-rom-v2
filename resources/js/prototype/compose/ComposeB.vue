<script setup lang="ts">
// PROTOTYPE (#467) — Variant B: the Audiences are named at the entry point (a menu:
// "Email whole Group", "Email officers", "Pick people…"), and the chosen one opens a
// side Sheet that walks three steps: Who → Message → Sent. Step 1 is the Audience
// already expanded into a To field of name chips (× removes a person, the way the
// winner of #467 was reacted to), with the count in the step heading and an "Add
// people" panel for the rest of the roster. No pre-send marking of unreachable
// Members; the result reports them.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PhArrowLeft, PhMagnifyingGlass, PhPlus, PhX } from '@phosphor-icons/vue';
import { computed, ref, toRef, watch } from 'vue';
import ComposerFields from './ComposerFields.vue';
import { fakeSend, fullName, initials, useDraft, useSelection, type ComposeContext } from './model';
import SendResult from './SendResult.vue';

const props = defineProps<{ context: ComposeContext; audience: string | null }>();
const open = defineModel<boolean>('open', { default: false });

const selection = useSelection(toRef(props, 'context'));
const draft = useDraft();
const step = ref<1 | 2 | 3>(1);
const adding = ref(false);
const search = ref('');

// Step 1 shows the recipients as chips; the "Add people" panel lists the roster with
// tick boxes and a search. With no Audience (Pick people…) the panel opens at once.
const listed = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.context.roster.filter((p) => q === '' || fullName(p).toLowerCase().includes(q));
});
const allTicked = computed(() => listed.value.length > 0 && listed.value.every((p) => selection.ticked.value.has(p.id)));
const tickListed = (on: boolean) => listed.value.forEach((p) => selection.toggle(p.id, on));

const stepTitle = computed(() => {
    if (props.context.fixed) return step.value === 1 ? `To ${fullName(props.context.fixed)}` : step.value === 2 ? 'Your message' : 'Sent';
    if (step.value === 1) return `Who · ${selection.recipients.value.length} Members`;
    if (step.value === 2) return `Message · to ${selection.recipients.value.length}`;
    return 'Sent';
});

const send = () => {
    draft.result.value = fakeSend(selection.recipients.value);
    step.value = 3;
};
const close = () => (open.value = false);
watch(open, (o) => {
    if (!o) return;
    draft.reset();
    step.value = 1;
    search.value = '';
    selection.pickAudience(props.audience);
    adding.value = props.audience === null;
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                    {{ context.fixed ? 'Direct message' : `Email · ${context.title}` }}
                </p>
                <SheetTitle>{{ stepTitle }}</SheetTitle>
                <ol class="text-muted-foreground flex gap-3 text-xs">
                    <li :class="step === 1 ? 'text-rom-ink font-semibold' : ''">1 Who</li>
                    <li :class="step === 2 ? 'text-rom-ink font-semibold' : ''">2 Message</li>
                    <li :class="step === 3 ? 'text-rom-ink font-semibold' : ''">3 Sent</li>
                </ol>
            </SheetHeader>

            <!-- Step 1 · Who -->
            <template v-if="step === 1">
                <div v-if="context.fixed" class="bg-muted/40 flex items-start gap-3 border p-3 text-sm">
                    <Avatar size="sm">
                        <AvatarImage v-if="context.fixed.photo" :src="context.fixed.photo" :alt="fullName(context.fixed)" />
                        <AvatarFallback>{{ initials(context.fixed) }}</AvatarFallback>
                    </Avatar>
                    <div>
                        <p>
                            <strong>{{ fullName(context.fixed) }}</strong>
                        </p>
                        <p class="text-muted-foreground mt-1">
                            You do not see their address. A reply comes to your own address, so replying shows it to you. That is their choice.
                        </p>
                    </div>
                </div>

                <template v-else>
                    <p class="text-sm">
                        <span v-if="selection.audience.value">
                            <strong>{{ selection.audienceLabel.value }}</strong>
                            <span class="text-muted-foreground"> · remove anyone to leave them out</span>
                        </span>
                        <span v-else class="text-muted-foreground">Pick people from {{ context.title }}</span>
                    </p>

                    <!-- The To field: every recipient as a chip, × removes them. -->
                    <div class="grid grid-cols-[max-content_1fr] items-start gap-x-3 text-sm">
                        <span class="text-muted-foreground pt-2">To</span>
                        <div class="border-input flex min-h-11 flex-wrap items-center gap-1.5 border px-2 py-1.5">
                            <span
                                v-for="p in selection.recipients.value"
                                :key="p.id"
                                class="bg-secondary text-secondary-foreground inline-flex items-center gap-1 px-2 py-0.5 text-sm"
                            >
                                {{ fullName(p) }}
                                <button
                                    type="button"
                                    class="hover:text-destructive"
                                    :aria-label="`Remove ${fullName(p)}`"
                                    @click="selection.toggle(p.id, false)"
                                >
                                    <PhX class="size-3" />
                                </button>
                            </span>
                            <span v-if="!selection.recipients.value.length" class="text-muted-foreground px-1">Nobody yet</span>
                            <Button type="button" variant="ghost" size="sm" class="ml-auto h-7 gap-1 px-2 text-xs" @click="adding = !adding">
                                <PhPlus class="size-3.5" /> Add people
                            </Button>
                        </div>
                    </div>

                    <!-- The add panel: the roster with tick boxes and a search. -->
                    <div v-if="adding" class="flex flex-col gap-2 border p-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="relative flex-1">
                                <PhMagnifyingGlass
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                />
                                <Input v-model="search" type="search" placeholder="Find a name" class="h-9 pl-9" />
                            </div>
                            <label class="text-muted-foreground flex items-center gap-2 text-sm">
                                <Checkbox :checked="allTicked" @update:checked="tickListed" /> All
                            </label>
                        </div>
                        <ul class="max-h-64 divide-y overflow-y-auto">
                            <li v-for="p in listed" :key="p.id">
                                <label class="hover:bg-muted/60 flex items-center gap-3 px-2 py-1.5 text-sm">
                                    <Checkbox
                                        :checked="selection.ticked.value.has(p.id)"
                                        @update:checked="(on: boolean) => selection.toggle(p.id, on)"
                                    />
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ p.last_name }}, {{ p.first_name }}
                                        <span v-if="p.hint" class="text-muted-foreground ml-1 text-xs">· {{ p.hint }}</span>
                                    </span>
                                </label>
                            </li>
                            <li v-if="!listed.length" class="text-muted-foreground px-3 py-4 text-center text-sm">Nobody matches.</li>
                        </ul>
                        <Button type="button" variant="ghost" size="sm" class="self-end" @click="adding = false">Done</Button>
                    </div>
                </template>

                <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                    <Button type="button" variant="ghost" @click="close">Cancel</Button>
                    <Button type="button" :disabled="selection.recipients.value.length === 0" @click="step = 2">
                        Next · write to {{ context.fixed ? context.fixed.first_name : selection.recipients.value.length }}
                    </Button>
                </div>
            </template>

            <!-- Step 2 · Message -->
            <template v-else-if="step === 2">
                <p class="bg-muted/40 border px-3 py-2 text-sm">
                    <span class="text-muted-foreground">To:</span> <strong>{{ selection.audienceLabel.value }}</strong>
                    <span v-if="!context.fixed" class="text-muted-foreground"> · {{ selection.recipients.value.length }} Members</span>
                    <br />
                    <span class="text-muted-foreground text-xs">From {{ context.fromName }} · replies come to you · you get a copy</span>
                </p>
                <ComposerFields :draft="draft" compact />
                <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                    <Button type="button" variant="ghost" class="gap-1.5" @click="step = 1"><PhArrowLeft class="size-4" /> Back</Button>
                    <Button type="button" :disabled="!draft.canSend.value" @click="send">Send</Button>
                </div>
            </template>

            <!-- Step 3 · Sent -->
            <SendResult
                v-else-if="draft.result.value"
                :result="draft.result.value"
                :direct="!!context.fixed"
                :audience-label="selection.audienceLabel.value"
                @done="close"
            />
        </SheetContent>
    </Sheet>
</template>
