<script setup lang="ts">
// PROTOTYPE (#467) — Variant C: select first, then compose. Tick boxes live on the host
// page itself (roster rows, Directory rows, sign-up chips); a floating "N selected · Email"
// bar carries the selection into a full-page takeover shaped like a mail client. Recipients
// are chips in a To field; named Audiences are quick-add buttons beside it. Legacy's
// select-all-then-send, made honest: the server would resolve the list, not the browser.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { PhMagnifyingGlass, PhPlus, PhX } from '@phosphor-icons/vue';
import { computed, ref, toRef, watch } from 'vue';
import ComposerFields from './ComposerFields.vue';
import { fakeSend, fullName, initials, useDraft, useSelection, type ComposeContext } from './model';
import SendResult from './SendResult.vue';

const props = defineProps<{ context: ComposeContext; seed: number[] }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ sent: [] }>();

const selection = useSelection(toRef(props, 'context'));
const draft = useDraft();
const picking = ref(false);
const search = ref('');

const rows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.context.roster.filter((p) => q === '' || fullName(p).toLowerCase().includes(q));
});

// Audience quick-add: union into the chips. Picking the same Audience again removes it.
const addAudience = (key: string) => {
    const a = props.context.audiences.find((x) => x.key === key)!;
    const has = a.members.every((m) => selection.ticked.value.has(m.id));
    a.members.forEach((m) => selection.toggle(m.id, !has));
    selection.audienceKey.value = has ? null : key;
};
const audienceOn = (key: string) => {
    const a = props.context.audiences.find((x) => x.key === key)!;
    return a.members.length > 0 && a.members.every((m) => selection.ticked.value.has(m.id));
};

const send = () => {
    draft.result.value = fakeSend(selection.recipients.value);
    emit('sent');
};
const close = () => (open.value = false);
watch(open, (o) => {
    if (!o) return;
    draft.reset();
    picking.value = false;
    search.value = '';
    selection.seed(props.seed);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="bg-background fixed inset-0 z-50 flex flex-col overflow-y-auto">
            <header class="bg-background sticky top-0 z-10 flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-6">
                <div class="flex flex-col">
                    <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                        {{ context.fixed ? 'Direct message' : 'New email' }}
                    </span>
                    <span class="text-rom-ink text-lg font-semibold">{{ context.title }}</span>
                </div>
                <div v-if="!draft.result.value" class="flex items-center gap-2">
                    <Button type="button" variant="ghost" @click="close">Discard</Button>
                    <Button type="button" :disabled="!draft.canSend.value || selection.recipients.value.length === 0" @click="send">
                        {{ context.fixed ? 'Send' : `Send to ${selection.recipients.value.length}` }}
                    </Button>
                </div>
            </header>

            <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <SendResult
                    v-if="draft.result.value"
                    :result="draft.result.value"
                    :direct="!!context.fixed"
                    :audience-label="selection.audienceLabel.value"
                    @done="close"
                />

                <template v-else>
                    <!-- The envelope: From is fixed, To is chips. -->
                    <dl class="grid grid-cols-[max-content_1fr] items-baseline gap-x-4 gap-y-3 text-sm">
                        <dt class="text-muted-foreground">From</dt>
                        <dd>{{ context.fromName }} <span class="text-muted-foreground">via DMV-ROM · replies come to you · you get a copy</span></dd>

                        <dt class="text-muted-foreground">To</dt>
                        <dd class="flex flex-col gap-2">
                            <div class="border-input flex min-h-11 flex-wrap items-center gap-1.5 border px-2 py-1.5">
                                <span
                                    v-for="p in selection.recipients.value"
                                    :key="p.id"
                                    class="bg-secondary text-secondary-foreground inline-flex items-center gap-1 px-2 py-0.5 text-sm"
                                >
                                    {{ fullName(p) }}
                                    <button
                                        v-if="!context.fixed"
                                        type="button"
                                        class="hover:text-destructive"
                                        :aria-label="`Remove ${fullName(p)}`"
                                        @click="selection.toggle(p.id, false)"
                                    >
                                        <PhX class="size-3" />
                                    </button>
                                </span>
                                <span v-if="!selection.recipients.value.length" class="text-muted-foreground px-1 text-sm">Nobody yet</span>
                                <Button
                                    v-if="!context.fixed"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="ml-auto h-7 gap-1 px-2 text-xs"
                                    @click="picking = !picking"
                                >
                                    <PhPlus class="size-3.5" /> Add people
                                </Button>
                            </div>

                            <p v-if="context.fixed" class="text-muted-foreground text-xs">
                                You do not see {{ context.fixed.first_name }}'s address. A reply comes to your own address, so replying shows it to
                                you. That is their choice.
                            </p>

                            <div v-if="context.audiences.length" class="flex flex-wrap items-center gap-1.5">
                                <span class="text-muted-foreground text-xs">Add an Audience:</span>
                                <Button
                                    v-for="a in context.audiences"
                                    :key="a.key"
                                    type="button"
                                    :variant="audienceOn(a.key) ? 'default' : 'outline'"
                                    size="sm"
                                    class="h-7 gap-1 px-2 text-xs"
                                    :title="a.note"
                                    @click="addAudience(a.key)"
                                >
                                    {{ a.name }} <span class="opacity-70">({{ a.members.length }})</span>
                                </Button>
                            </div>

                            <div v-if="picking" class="flex flex-col gap-2 border p-2">
                                <div class="relative">
                                    <PhMagnifyingGlass
                                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    />
                                    <Input v-model="search" type="search" placeholder="Find a name" class="h-9 pl-9" />
                                </div>
                                <ul class="max-h-56 divide-y overflow-y-auto">
                                    <li v-for="p in rows" :key="p.id">
                                        <label class="hover:bg-muted/60 flex items-center gap-3 px-2 py-1.5 text-sm">
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
                                        </label>
                                    </li>
                                </ul>
                                <Button type="button" variant="ghost" size="sm" class="self-end" @click="picking = false">Done</Button>
                            </div>
                        </dd>
                    </dl>

                    <ComposerFields :draft="draft" />
                </template>
            </div>
        </div>
    </Teleport>
</template>
