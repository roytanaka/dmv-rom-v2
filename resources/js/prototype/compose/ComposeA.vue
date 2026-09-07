<script setup lang="ts">
// PROTOTYPE (#467) — Variant A: one "Send email" button in the page header opens a wide
// centred Dialog. Picker on the left (named Audiences above a ticked roster), composer on
// the right, the resolved count in the footer. Unreachable Members are marked in the
// roster before send, so the officer knows before they press the button.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { PhMagnifyingGlass } from '@phosphor-icons/vue';
import { computed, ref, toRef, watch } from 'vue';
import ComposerFields from './ComposerFields.vue';
import { fakeSend, fullName, initials, unreachable, useDraft, useSelection, type ComposeContext } from './model';
import SendResult from './SendResult.vue';

const props = defineProps<{ context: ComposeContext }>();
const open = defineModel<boolean>('open', { default: false });

const selection = useSelection(toRef(props, 'context'));
const draft = useDraft();
const search = ref('');

const rows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.context.roster.filter((p) => q === '' || fullName(p).toLowerCase().includes(q));
});
const allTicked = computed(() => props.context.roster.length > 0 && props.context.roster.every((p) => selection.ticked.value.has(p.id)));
const reachable = computed(() => selection.recipients.value.filter((p) => !unreachable(p)).length);

const send = () => (draft.result.value = fakeSend(selection.recipients.value));
const close = () => {
    open.value = false;
};
watch(open, (o) => {
    if (!o) return;
    draft.reset();
    selection.clear();
    search.value = '';
    if (props.context.audiences.length === 1) selection.pickAudience(props.context.audiences[0].key);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent :class="context.fixed ? 'sm:max-w-xl' : 'sm:max-w-5xl'" class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ context.fixed ? `Message ${context.fixed.first_name}` : `Email · ${context.title}` }}</DialogTitle>
            </DialogHeader>

            <SendResult
                v-if="draft.result.value"
                :result="draft.result.value"
                :direct="!!context.fixed"
                :audience-label="selection.audienceLabel.value"
                @done="close"
            />

            <div v-else class="grid gap-6" :class="context.fixed ? '' : 'lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]'">
                <!-- Direct message: one fixed recipient, no address, the disclosure note. -->
                <div v-if="context.fixed" class="bg-muted/40 flex items-start gap-3 border p-3">
                    <Avatar size="sm">
                        <AvatarImage v-if="context.fixed.photo" :src="context.fixed.photo" :alt="fullName(context.fixed)" />
                        <AvatarFallback>{{ initials(context.fixed) }}</AvatarFallback>
                    </Avatar>
                    <div class="text-sm">
                        <p>
                            <span class="text-muted-foreground">To:</span> <strong>{{ fullName(context.fixed) }}</strong>
                        </p>
                        <p class="text-muted-foreground mt-1">
                            The app sends this for you. You do not see {{ context.fixed.first_name }}'s address. If they reply, their reply comes to
                            your own address, so replying shows it to you. That is their choice.
                        </p>
                    </div>
                </div>

                <!-- The picker: Audiences, then the roster with select-all. -->
                <div v-else class="flex flex-col gap-4">
                    <fieldset v-if="context.audiences.length" class="flex flex-col gap-1">
                        <legend class="text-muted-foreground mb-1 text-xs font-semibold tracking-wide uppercase">Audience</legend>
                        <button
                            v-for="a in context.audiences"
                            :key="a.key"
                            type="button"
                            class="hover:bg-muted flex items-center justify-between gap-2 border px-3 py-2 text-left text-sm"
                            :class="selection.audienceKey.value === a.key ? 'border-rom-slate bg-muted font-medium' : 'border-transparent'"
                            @click="selection.pickAudience(a.key)"
                        >
                            <span>
                                {{ a.name }}
                                <span v-if="a.note" class="text-muted-foreground ml-1 text-xs">· {{ a.note }}</span>
                            </span>
                            <span class="text-muted-foreground tabular-nums">{{ a.members.length }}</span>
                        </button>
                    </fieldset>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                {{ context.audiences.length ? 'Or pick people' : 'People' }}
                            </span>
                            <label class="text-muted-foreground flex items-center gap-2 text-sm">
                                <Checkbox :checked="allTicked" @update:checked="selection.tickAll" /> Select all
                            </label>
                        </div>
                        <div class="relative">
                            <PhMagnifyingGlass class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input v-model="search" type="search" placeholder="Find a name" class="h-9 pl-9" />
                        </div>
                        <ul class="max-h-72 divide-y overflow-y-auto border">
                            <li v-for="p in rows" :key="p.id">
                                <label
                                    class="hover:bg-muted/60 flex items-center gap-3 px-3 py-1.5 text-sm"
                                    :class="unreachable(p) ? 'opacity-60' : ''"
                                >
                                    <Checkbox
                                        :checked="selection.ticked.value.has(p.id)"
                                        @update:checked="(on: boolean) => selection.toggle(p.id, on)"
                                    />
                                    <Avatar size="sm" class="size-6">
                                        <AvatarImage v-if="p.photo" :src="p.photo" :alt="fullName(p)" />
                                        <AvatarFallback class="text-[10px]">{{ initials(p) }}</AvatarFallback>
                                    </Avatar>
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ p.last_name }}, {{ p.first_name }}
                                        <span v-if="p.hint" class="text-muted-foreground ml-1 text-xs">· {{ p.hint }}</span>
                                    </span>
                                    <span v-if="unreachable(p)" class="text-muted-foreground text-xs">cannot be reached</span>
                                </label>
                            </li>
                            <li v-if="!rows.length" class="text-muted-foreground px-3 py-4 text-center text-sm">Nobody matches.</li>
                        </ul>
                    </div>
                </div>

                <ComposerFields :draft="draft" compact />
            </div>

            <!-- Footer: the resolved count is the thing to watch, so it sits beside Send. -->
            <div v-if="!draft.result.value" class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p class="text-sm">
                    <span class="text-muted-foreground">To:</span>
                    <strong>{{ selection.audienceLabel.value }}</strong>
                    <span v-if="!context.fixed" class="text-muted-foreground"> · {{ selection.recipients.value.length }} Members</span>
                    <span v-if="!context.fixed && reachable !== selection.recipients.value.length" class="text-muted-foreground">
                        ({{ selection.recipients.value.length - reachable }} cannot be reached)
                    </span>
                </p>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground hidden text-xs sm:inline"
                        >From {{ context.fromName }} · replies come to you · you get a copy</span
                    >
                    <Button type="button" variant="ghost" @click="close">Cancel</Button>
                    <Button type="button" :disabled="!draft.canSend.value || selection.recipients.value.length === 0" @click="send">
                        {{ context.fixed ? 'Send message' : `Send to ${selection.recipients.value.length}` }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
