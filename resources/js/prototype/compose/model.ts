// PROTOTYPE (#467) — the compose context every entry point hands the composer, and a
// fake send. No server. The vocabulary follows the map (#458): Broadcast, Audience,
// Direct message, no-email flag.
import { computed, ref, type Ref } from 'vue';

export interface Person {
    id: number;
    first_name: string;
    last_name: string;
    photo?: string | null;
    // A hint the picker may show: roles or standing, as the host page has them.
    hint?: string;
}

export interface Audience {
    key: string;
    name: string;
    // Who is in it, resolved from the host page's own data.
    members: Person[];
    // Records-only Audiences read as such so the prototype can show the gate.
    note?: string;
}

export interface ComposeContext {
    // Where compose was opened from. `member` is the Direct-message case.
    kind: 'group' | 'schedule' | 'shift' | 'directory' | 'member';
    // The page's own name: the Group, the Schedule, the Shift's time, or the Directory.
    title: string;
    // The From display name the mail will carry (charting item 9).
    fromName: string;
    audiences: Audience[];
    // The hand-pick roster under the Audiences (empty for a Direct message).
    roster: Person[];
    // Direct message: the one fixed recipient.
    fixed?: Person;
}

export const fullName = (p: Person) => `${p.first_name} ${p.last_name}`;
export const initials = (p: Person) => `${p.first_name.charAt(0)}${p.last_name.charAt(0)}`.toUpperCase();

// The no-email flag, faked: every seventh Member cannot be reached. Deterministic so
// the same names are flagged on every page and every variant.
export const unreachable = (p: Person) => p.id % 7 === 3;

export const dedupe = (people: Person[]) => {
    const seen = new Set<number>();
    return people.filter((p) => (seen.has(p.id) ? false : (seen.add(p.id), true)));
};

export interface SendResult {
    sent: number;
    skipped: Person[];
}

export const fakeSend = (recipients: Person[]): SendResult => {
    const skipped = recipients.filter(unreachable);
    return { sent: recipients.length - skipped.length, skipped };
};

// The selection every picker variant edits: a chosen Audience (or none), the ticked
// set, and whether the ticked set still equals the Audience (the "edited" flag the
// sent record keeps, #466). One composable so the three variants disagree about
// layout, not about state.
export const useSelection = (context: Ref<ComposeContext>) => {
    const audienceKey = ref<string | null>(null);
    const ticked = ref<Set<number>>(new Set());

    const audience = computed(() => context.value.audiences.find((a) => a.key === audienceKey.value) ?? null);

    const pickAudience = (key: string | null) => {
        audienceKey.value = key;
        const a = context.value.audiences.find((x) => x.key === key);
        ticked.value = new Set(a ? a.members.map((m) => m.id) : []);
    };
    const toggle = (id: number, on: boolean) => {
        const next = new Set(ticked.value);
        if (on) next.add(id);
        else next.delete(id);
        ticked.value = next;
    };
    const tickAll = (on: boolean) => {
        ticked.value = new Set(on ? context.value.roster.map((p) => p.id) : []);
        if (on) audienceKey.value = null;
    };
    const seed = (ids: number[]) => {
        audienceKey.value = null;
        ticked.value = new Set(ids);
    };
    const clear = () => pickAudience(null);

    // Everyone addressable from this page: the roster plus every Audience's members
    // (an org-wide Audience can reach people the page does not list).
    const everyone = computed(() => dedupe([...context.value.roster, ...context.value.audiences.flatMap((a) => a.members)]));
    const recipients = computed(() => (context.value.fixed ? [context.value.fixed] : everyone.value.filter((p) => ticked.value.has(p.id))));
    const edited = computed(() => {
        if (!audience.value) return false;
        const ids = new Set(audience.value.members.map((m) => m.id));
        return ids.size !== ticked.value.size || [...ids].some((id) => !ticked.value.has(id));
    });
    // What the sent record will say for the Audience (#466 item 1).
    const audienceLabel = computed(() => {
        if (context.value.fixed) return fullName(context.value.fixed);
        if (!audience.value) return `Hand-picked (${recipients.value.length})`;
        const removed = audience.value.members.length - recipients.value.length;
        return edited.value ? `${audience.value.name}, ${removed} unticked` : audience.value.name;
    });

    return { audienceKey, audience, ticked, pickAudience, toggle, tickAll, seed, clear, everyone, recipients, edited, audienceLabel };
};

// The composer's own state: subject, body, attachments, and the result once "sent".
export const useDraft = () => {
    const subject = ref('');
    const body = ref('');
    const files = ref<File[]>([]);
    const result = ref<SendResult | null>(null);
    const totalBytes = computed(() => files.value.reduce((n, f) => n + f.size, 0));
    const tooBig = computed(() => totalBytes.value > 10 * 1024 * 1024);
    const addFiles = (list: FileList | null) => {
        if (!list) return;
        files.value = [...files.value, ...Array.from(list)].slice(0, 2);
    };
    const removeFile = (i: number) => files.value.splice(i, 1);
    const canSend = computed(() => subject.value.trim() !== '' && body.value.trim() !== '' && !tooBig.value);
    const reset = () => {
        subject.value = '';
        body.value = '';
        files.value = [];
        result.value = null;
    };
    return { subject, body, files, result, totalBytes, tooBig, addFiles, removeFile, canSend, reset };
};

export const formatBytes = (n: number) => (n < 1024 * 1024 ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / 1024 / 1024).toFixed(1)} MB`);
