<script setup lang="ts">
// The composer sheet (#489, ADR-0024 §6) — a right-hand sheet stepped Who → Message → Sent,
// the host page still visible behind it. Opened by the Email menu with an Audience (or null
// for a hand-pick); it names the Audience, fills a To field of removable name chips, and lets
// the sender tick more from the page's roster. The browser never posts a recipient list: it
// posts the Audience key and the per-Member edits, and the server re-resolves who is reached
// (§5). The selection maths live in ./composer.ts so they unit-test off the framework.
//
// Context-driven by props (context, contextSubject, roster), so the roster, Schedule, Shift,
// and Directory entry points in the next tickets mount the same sheet unchanged (AC).
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import RichTextEditor from '@/emailing/RichTextEditor.vue';
import {
    audienceEdits,
    audienceLabelDescriptor,
    canSend,
    cappedChips,
    filterRoster,
    recipientName,
    rosterIds,
    type AudienceOption,
    type Recipient,
} from '@/emailing/composer';
import { PhMagnifyingGlass, PhPaperclip, PhX } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    context: string;
    contextSubject: string | null;
    groupName: string;
    roster: Recipient[];
    audience: AudienceOption | null;
    // A Direct message (#491, ADR-0024 §6): one fixed recipient, chosen by opening the sheet from
    // their profile rather than picked from a roster. When set, the Who step shows them and nothing
    // else, and Next goes straight to Message. Absent for a Broadcast.
    fixed?: Recipient | null;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

type Step = 'who' | 'message' | 'sent';

const step = ref<Step>('who');
const baseIds = ref<number[]>([]);
const ticked = ref<Set<number>>(new Set());
const directory = ref<Map<number, Recipient>>(new Map());
const panelOpen = ref(false);
const search = ref('');
// The Message step lists the recipients read-only, capped, with a "+N more" reveal (#507).
const chipsExpanded = ref(false);

const subject = ref('');
const body = ref('');
const attachments = ref<File[]>([]);

const sending = ref(false);
const queued = ref(0);
const skipped = ref<string[]>([]);
const errorMessage = ref('');

// (Re)seed the sheet each time it opens. A Direct message seeds its one fixed recipient and shows
// no roster; a named Audience fetches its resolved rows to pre-tick and to name the chips; a
// hand-pick opens on an empty To field with the Add-people panel already open (ADR-0024 §6).
watch(
    () => [props.open, props.audience, props.fixed] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        step.value = 'who';
        search.value = '';
        chipsExpanded.value = false;
        subject.value = '';
        body.value = '';
        attachments.value = [];
        queued.value = 0;
        skipped.value = [];
        errorMessage.value = '';
        directory.value = new Map(props.roster.map((member) => [member.id, member]));

        if (props.fixed) {
            directory.value = new Map([[props.fixed.id, props.fixed]]);
            baseIds.value = [props.fixed.id];
            ticked.value = new Set([props.fixed.id]);
            panelOpen.value = false;
            return;
        }

        if (props.audience === null) {
            baseIds.value = [];
            ticked.value = new Set();
            panelOpen.value = true;
            return;
        }

        panelOpen.value = false;
        void loadRecipients(props.audience);
    },
    { immediate: true },
);

// Fetch the Audience's resolved recipients (ADR-0024 §5) — pre-ticked, and merged into the
// directory so the To chips have names. The no-email skipped are not surfaced here; they are
// reported after send.
async function loadRecipients(audience: AudienceOption): Promise<void> {
    const query = new URLSearchParams({ context: props.context });
    if (props.contextSubject !== null) {
        query.set('subject', props.contextSubject);
    }
    if (audience.parameter !== null) {
        query.set('parameter', audience.parameter);
    }

    const response = await fetch(`${route('audiences.show', { audience: audience.key })}?${query.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return;
    }

    const data = (await response.json()) as { recipients: Recipient[] };
    const merged = new Map(directory.value);
    for (const recipient of data.recipients) {
        merged.set(recipient.id, recipient);
    }
    directory.value = merged;
    baseIds.value = data.recipients.map((recipient) => recipient.id);
    ticked.value = new Set(baseIds.value);
}

const recipientCount = computed(() => ticked.value.size);

const chips = computed<Recipient[]>(() =>
    [...ticked.value].map((id) => directory.value.get(id)).filter((member): member is Recipient => member !== undefined),
);

// The Message step's read-only chips: capped, with the overflow the "+N more" control reveals.
const messageChips = computed(() => cappedChips(chips.value, chipsExpanded.value));

const filteredRoster = computed(() => filterRoster(props.roster, search.value));
const allTicked = computed(() => props.roster.length > 0 && props.roster.every((member) => ticked.value.has(member.id)));

// The stored label, live (ADR-0024 §6): a hand-picked count, the bare Audience name, or the
// name with the number of rows removed.
const audienceLabel = computed(() => {
    const descriptor = audienceLabelDescriptor(props.audience, baseIds.value, ticked.value);

    if (descriptor.kind === 'hand_picked') {
        return trans('broadcasts.composer.hand_picked', { count: String(descriptor.count) });
    }
    if (descriptor.kind === 'edited') {
        return trans('audience.edited', { label: descriptor.label, count: String(descriptor.removed) });
    }
    return descriptor.label;
});

const sendable = computed(() => canSend({ subject: subject.value, body: body.value, recipientCount: recipientCount.value }));

function retick(next: Set<number>): void {
    ticked.value = next;
}

function removeChip(id: number): void {
    const next = new Set(ticked.value);
    next.delete(id);
    retick(next);
}

function toggleMember(id: number, on: boolean): void {
    const next = new Set(ticked.value);
    if (on) {
        next.add(id);
    } else {
        next.delete(id);
    }
    retick(next);
}

function toggleAll(on: boolean): void {
    if (on) {
        retick(new Set([...ticked.value, ...rosterIds(props.roster)]));
        return;
    }
    const remaining = new Set(ticked.value);
    for (const id of rosterIds(props.roster)) {
        remaining.delete(id);
    }
    retick(remaining);
}

function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    attachments.value = [...attachments.value, ...Array.from(input.files ?? [])];
    input.value = '';
}

function removeAttachment(index: number): void {
    attachments.value = attachments.value.filter((_, i) => i !== index);
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// The CSRF token Laravel puts on the XSRF-TOKEN cookie, for the one POST — the read seams are
// GETs and need none. Mirrors what Inertia/Axios send automatically.
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function send(): Promise<void> {
    if (!sendable.value || sending.value) {
        return;
    }

    sending.value = true;

    const edits = audienceEdits(baseIds.value, ticked.value);
    const form = new FormData();
    form.set('context', props.context);
    if (props.contextSubject !== null) {
        form.set('context_subject', props.contextSubject);
    }
    form.set('subject', subject.value);
    form.set('body', body.value);
    form.set('audience', props.audience?.key ?? 'hand_picked');
    if (props.audience?.parameter != null) {
        form.set('parameter', props.audience.parameter);
    }
    for (const id of edits.removed) {
        form.append('removed[]', String(id));
    }
    for (const id of edits.added) {
        form.append('added[]', String(id));
    }
    for (const file of attachments.value) {
        form.append('attachments[]', file);
    }

    try {
        const response = await fetch(route('broadcasts.store'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrfToken() },
            body: form,
        });

        if (!response.ok) {
            // A Direct message to a Member with email switched off is refused (ADR-0024 §6): surface
            // the server's "cannot be reached" message rather than failing silently.
            if (response.status === 422) {
                const problem = (await response.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
                errorMessage.value = problem?.errors?.audience?.[0] ?? problem?.message ?? '';
            }
            return;
        }

        const data = (await response.json()) as { queued: number; skipped: string[] };
        queued.value = data.queued;
        skipped.value = data.skipped;
        step.value = 'sent';
    } finally {
        sending.value = false;
    }
}

function close(): void {
    emit('update:open', false);
}
</script>

<template>
    <Sheet :open="open" @update:open="(value: boolean) => emit('update:open', value)">
        <SheetContent class="flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md">
            <SheetHeader class="mb-4">
                <SheetTitle>{{ trans('broadcasts.composer.title') }}</SheetTitle>
            </SheetHeader>

            <!-- Who, a Direct message: one fixed recipient, no picking (ADR-0024 §6). -->
            <div v-if="step === 'who' && fixed" class="flex flex-1 flex-col gap-4">
                <div class="flex items-center gap-3">
                    <Avatar size="base">
                        <AvatarImage v-if="fixed.photo" :src="fixed.photo" :alt="recipientName(fixed)" />
                        <AvatarFallback>{{ recipientName(fixed).charAt(0) }}</AvatarFallback>
                    </Avatar>
                    <p class="text-base font-semibold">{{ recipientName(fixed) }}</p>
                </div>
                <p class="text-muted-foreground text-sm">{{ trans('broadcasts.composer.direct_reply_note', { name: fixed.first_name }) }}</p>

                <div class="mt-auto flex justify-end pt-4">
                    <Button type="button" @click="step = 'message'">{{ trans('broadcasts.composer.next') }}</Button>
                </div>
            </div>

            <!-- Who -->
            <div v-else-if="step === 'who'" class="flex flex-1 flex-col gap-4">
                <div>
                    <h3 class="text-base font-semibold">
                        {{ transChoice('broadcasts.composer.who_heading', recipientCount, { count: String(recipientCount) }) }}
                    </h3>
                    <p class="text-muted-foreground text-sm">{{ audienceLabel }}</p>
                </div>

                <div>
                    <Label class="mb-1 block">{{ trans('broadcasts.composer.to') }}</Label>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge v-for="member in chips" :key="member.id" variant="secondary" class="gap-1 font-normal">
                            {{ recipientName(member) }}
                            <button
                                type="button"
                                class="hover:text-foreground -mr-0.5 rounded-full transition-colors"
                                :aria-label="trans('broadcasts.composer.remove', { name: recipientName(member) })"
                                @click="removeChip(member.id)"
                            >
                                <PhX class="size-3" />
                            </button>
                        </Badge>
                    </div>
                </div>

                <div>
                    <Button v-if="!panelOpen" type="button" variant="outline" size="sm" @click="panelOpen = true">
                        {{ trans('broadcasts.composer.add_people') }}
                    </Button>

                    <div v-else class="border-input flex flex-col gap-2 border p-2">
                        <div class="relative">
                            <PhMagnifyingGlass class="text-muted-foreground absolute top-1/2 left-2 size-4 -translate-y-1/2" />
                            <Input v-model="search" class="h-9 pl-8" :placeholder="trans('broadcasts.composer.search_placeholder')" />
                        </div>
                        <label class="border-input flex items-center gap-2 border-b pb-2 text-sm font-medium">
                            <Checkbox :checked="allTicked" @update:checked="toggleAll" />
                            {{ trans('broadcasts.composer.select_all') }}
                        </label>
                        <ul class="max-h-64 overflow-y-auto">
                            <li v-for="member in filteredRoster" :key="member.id">
                                <label class="flex items-center gap-2 py-1.5 text-sm">
                                    <Checkbox :checked="ticked.has(member.id)" @update:checked="(on: boolean) => toggleMember(member.id, on)" />
                                    <Avatar size="sm">
                                        <AvatarImage v-if="member.photo" :src="member.photo" :alt="recipientName(member)" />
                                        <AvatarFallback>{{ recipientName(member).charAt(0) }}</AvatarFallback>
                                    </Avatar>
                                    {{ recipientName(member) }}
                                </label>
                            </li>
                            <li v-if="filteredRoster.length === 0" class="text-muted-foreground py-2 text-sm">
                                {{ trans('broadcasts.composer.empty_roster') }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-auto flex justify-end gap-2 pt-4">
                    <Button type="button" :disabled="recipientCount === 0" @click="step = 'message'">{{ trans('broadcasts.composer.next') }}</Button>
                </div>
            </div>

            <!-- Message -->
            <div v-else-if="step === 'message'" class="flex flex-1 flex-col gap-4">
                <div class="text-sm">
                    <p class="font-medium">{{ transChoice('broadcasts.composer.who_heading', recipientCount, { count: String(recipientCount) }) }}</p>
                    <p class="text-muted-foreground">{{ audienceLabel }}</p>
                    <!-- The recipients, read-only (Back returns to Who to edit), capped with a "+N more"
                         reveal. A Direct message shows its one fixed recipient in the heading, unchanged. -->
                    <div v-if="!fixed" class="mt-2 flex flex-wrap gap-1.5">
                        <Badge v-for="member in messageChips.shown" :key="member.id" variant="secondary" class="font-normal">
                            {{ recipientName(member) }}
                        </Badge>
                        <button
                            v-if="messageChips.hidden > 0"
                            type="button"
                            class="text-rom-slate text-xs font-semibold hover:underline"
                            @click="chipsExpanded = true"
                        >
                            {{ trans('broadcasts.composer.more', { count: String(messageChips.hidden) }) }}
                        </button>
                    </div>
                    <p class="text-muted-foreground mt-1">{{ trans('broadcasts.composer.from', { group: groupName }) }}</p>
                </div>

                <div>
                    <Label for="composer-subject" class="mb-1 block">{{ trans('broadcasts.composer.subject') }}</Label>
                    <Input id="composer-subject" v-model="subject" />
                </div>

                <div>
                    <Label class="mb-1 block">{{ trans('broadcasts.composer.body') }}</Label>
                    <RichTextEditor v-model="body" />
                </div>

                <div>
                    <Label class="mb-1 block">{{ trans('broadcasts.composer.attachments') }}</Label>
                    <ul v-if="attachments.length" class="mb-2 flex flex-col gap-1">
                        <li v-for="(file, index) in attachments" :key="index" class="flex items-center justify-between text-sm">
                            <span
                                >{{ file.name }} <span class="text-muted-foreground">({{ formatSize(file.size) }})</span></span
                            >
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="trans('broadcasts.composer.attachment_remove', { name: file.name })"
                                @click="removeAttachment(index)"
                            >
                                <PhX class="size-3" />
                            </button>
                        </li>
                    </ul>
                    <label class="text-rom-slate inline-flex cursor-pointer items-center gap-1.5 text-sm">
                        <PhPaperclip class="size-4" />
                        {{ trans('broadcasts.composer.attach') }}
                        <input type="file" multiple class="hidden" @change="onFiles" />
                    </label>
                </div>

                <p v-if="errorMessage" class="text-destructive text-sm">{{ errorMessage }}</p>

                <div class="mt-auto flex justify-between gap-2 pt-4">
                    <Button type="button" variant="outline" @click="step = 'who'">{{ trans('broadcasts.composer.back') }}</Button>
                    <Button type="button" :disabled="!sendable || sending" @click="send">
                        {{ sending ? trans('broadcasts.composer.sending') : trans('broadcasts.composer.send') }}
                    </Button>
                </div>
            </div>

            <!-- Sent -->
            <div v-else class="flex flex-1 flex-col gap-4">
                <div>
                    <h3 class="text-base font-semibold">{{ transChoice('broadcasts.composer.sent_heading', queued, { count: String(queued) }) }}</h3>
                    <p class="text-muted-foreground text-sm">{{ trans('broadcasts.composer.sent_note') }}</p>
                </div>

                <p v-if="skipped.length" class="text-sm">{{ skipped.length }} {{ trans('broadcasts.composer.skipped') }}: {{ skipped.join(', ') }}</p>

                <div class="mt-auto flex justify-end pt-4">
                    <Button type="button" @click="close">{{ trans('broadcasts.composer.done') }}</Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
