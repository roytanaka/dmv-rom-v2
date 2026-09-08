<script setup lang="ts">
// Group Roster tab (#189, #192, PRD #186) — the Group-scoped instance of the
// Directory surface. Reuses the Directory's row presentation (avatar, name linking
// through to the profile, contact gated by `viewContact`) plus its name filter and
// A–Z jump rail, so a member doesn't learn a new surface. Two Group-specific
// additions per row: the role badge(s) the member holds in this Group, and a
// standing badge shown only when the within-Group standing isn't Full.
//
// Ordering is pure A–Z by surname (officers are surfaced on the Overview, not floated
// here). The payload arrives pre-sorted from the server; the rail re-derives its
// letter buckets from the same surname so the two can never disagree. Contact PII is
// present in a row only when the server deemed the viewer authorized — this surface
// renders what it's given and computes no authority.
//
// Officers (Secretary / Chair / super-tier) get inline CRUD on top of the read
// surface, gated entirely by the server's `can.manageRoster` hint: a "show past
// members" toggle that reveals Resigned, an "Add member" search, and a per-row
// manage menu (change standing with a leave window, assign/revoke roles, resign, and
// the added-in-error hard-remove). Every mutation is enforced by the
// GroupMemberPolicy regardless of what renders.
import AlphaJumpRail from '@/components/AlphaJumpRail.vue';
import GroupStandingBadge from '@/components/GroupStandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type RosterMember, type RosterMeta } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { PhDotsThree, PhMagnifyingGlass, PhPlus } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ members: RosterMember[]; canManage: boolean; meta: RosterMeta; groupSlug: string }>();

// The standings an officer may set directly. Resigned is reached through the Resign
// action (it keeps history), and Deceased is never set here — both are excluded.
const SETTABLE_STANDINGS = ['full', 'loa', 'trainee', 'transitional', 'auxiliary', 'projects', 'emeritus', 'inactive', 'donor'] as const;

// Live name search over the loaded roster — a case-insensitive substring of the full
// name. Nothing here touches the server.
const search = ref('');

const filteredMembers = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') return props.members;
    return props.members.filter((member) => `${member.first_name} ${member.last_name}`.toLowerCase().includes(needle));
});

// The A–Z bucket for a surname: its first letter, accent-folded (so "Étienne" jumps
// under E) and uppercased. Non-letter or empty names fall outside A–Z.
const jumpLetter = (member: RosterMember) =>
    member.last_name
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .charAt(0)
        .toUpperCase();

const availableLetters = computed(() => [...new Set(filteredMembers.value.map(jumpLetter))]);

// The first row id under each letter, so a rail tap scrolls straight to the bucket.
const firstIdByLetter = computed(() => {
    const map = new Map<string, number>();
    for (const member of filteredMembers.value) {
        const letter = jumpLetter(member);
        if (!map.has(letter)) map.set(letter, member.id);
    }
    return map;
});

const anchorId = (member: RosterMember) => {
    const letter = jumpLetter(member);
    return firstIdByLetter.value.get(letter) === member.id ? `roster-letter-${letter}` : undefined;
};

const jumpTo = (letter: string) => {
    document.getElementById(`roster-letter-${letter}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const initials = (member: RosterMember) => `${member.first_name.charAt(0)}${member.last_name.charAt(0)}`.toUpperCase();

const displayName = (member: RosterMember) => `${member.last_name}, ${member.first_name}`;

const hasContact = (member: RosterMember) => member.email !== undefined || member.phone !== undefined;

// Empty-state colspan tracks the optional officer Actions column.
const columnCount = computed(() => (props.canManage ? 6 : 5));

// Native select styling shared by both the add and manage dialogs.
const SELECT_CLASS =
    'border-input bg-background focus-visible:border-rom-slate focus-visible:ring-rom-slate-50 flex h-11 w-full rounded-none border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden';

// --- Officer CRUD (#192) ----------------------------------------------------

// Show-past toggle: a server round-trip that re-resolves the roster with (or
// without) the Resigned reveal. The officer-only gate also lives server-side.
const toggleShowPast = (next: boolean) => {
    const params = next ? { group: props.groupSlug, section: 'roster', past: 1 } : { group: props.groupSlug, section: 'roster' };
    router.get(route('groups.show', params), {}, { preserveScroll: true, preserveState: false });
};

// Toggle a role on or off within a form's role array (the shared assign/revoke
// control for both the add and manage dialogs).
const toggleRole = (roles: string[], role: string, on: boolean) => {
    const index = roles.indexOf(role);
    if (on && index === -1) roles.push(role);
    if (!on && index !== -1) roles.splice(index, 1);
};

// Add member — search the Group's non-members, set standing (default Full), and
// optionally assign roles on add.
const addOpen = ref(false);
const candidateSearch = ref('');
const addForm = useForm<{ member_id: number | null; status: string; roles: string[] }>({ member_id: null, status: 'full', roles: [] });

const candidates = computed(() => {
    const needle = candidateSearch.value.trim().toLowerCase();
    if (needle === '') return props.meta.candidates;
    return props.meta.candidates.filter((candidate) => `${candidate.first_name} ${candidate.last_name}`.toLowerCase().includes(needle));
});

const openAdd = () => {
    addForm.reset();
    addForm.clearErrors();
    candidateSearch.value = '';
    addOpen.value = true;
};

const submitAdd = () => {
    addForm.post(route('group-members.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => (addOpen.value = false),
    });
};

// Manage a membership — change standing, set a leave window, assign/revoke roles.
const editMember = ref<RosterMember | null>(null);
const editForm = useForm<{ status: string; loa_start: string; loa_end: string; roles: string[] }>({
    status: 'full',
    loa_start: '',
    loa_end: '',
    roles: [],
});

const editOpen = computed({
    get: () => editMember.value !== null,
    set: (open: boolean) => {
        if (!open) editMember.value = null;
    },
});

const openManage = (member: RosterMember) => {
    editForm.status = member.group_standing;
    editForm.loa_start = member.loa_start ?? '';
    editForm.loa_end = member.loa_end ?? '';
    editForm.roles = [...member.group_roles];
    editForm.clearErrors();
    editMember.value = member;
};

const submitManage = () => {
    if (editMember.value === null) return;
    editForm
        .transform((data) => ({ ...data, loa_start: data.loa_start || null, loa_end: data.loa_end || null }))
        .patch(route('group-members.update', { membership: editMember.value.membership_id }), {
            preserveScroll: true,
            onSuccess: () => (editMember.value = null),
        });
};

// Resign — the default soft "remove": a status change that keeps the row and its
// history, so a resigned member can later be reinstated.
const resign = (member: RosterMember) => {
    if (window.confirm(trans('group.roster.confirm_resign'))) {
        router.patch(route('group-members.update', { membership: member.membership_id }), { status: 'resigned' }, { preserveScroll: true });
    }
};

// Hard-remove — the true row delete, offered only for an added-in-error membership
// with no dependent records (`can_hard_remove`).
const hardRemove = (member: RosterMember) => {
    if (window.confirm(trans('group.roster.confirm_remove'))) {
        router.delete(route('group-members.destroy', { membership: member.membership_id }), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative sm:max-w-xs sm:flex-1">
                <PhMagnifyingGlass class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="trans('group.roster.search.placeholder')"
                    :aria-label="trans('group.roster.search.label')"
                    class="pl-9"
                />
            </div>

            <!-- Officer controls: show-past toggle + add member (#192). -->
            <div v-if="canManage" class="flex items-center gap-4">
                <label class="text-muted-foreground flex items-center gap-2 text-sm">
                    <Checkbox :checked="meta.showingPast" @update:checked="toggleShowPast" />
                    {{ trans('group.roster.show_past') }}
                </label>
                <Button type="button" size="sm" class="gap-1.5" @click="openAdd">
                    <PhPlus class="size-4" />
                    {{ trans('group.roster.add') }}
                </Button>
            </div>
        </div>

        <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12"
                                ><span class="sr-only">{{ trans('group.roster.column.name') }}</span></TableHead
                            >
                            <TableHead>{{ trans('group.roster.column.name') }}</TableHead>
                            <TableHead>{{ trans('group.roster.column.roles') }}</TableHead>
                            <TableHead>{{ trans('group.roster.column.contact') }}</TableHead>
                            <TableHead>{{ trans('group.roster.column.standing') }}</TableHead>
                            <TableHead v-if="canManage" class="w-12 text-right"
                                ><span class="sr-only">{{ trans('group.roster.column.actions') }}</span></TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="member in filteredMembers" :id="anchorId(member)" :key="member.id" class="scroll-mt-24">
                            <TableCell>
                                <Avatar size="sm">
                                    <AvatarImage v-if="member.photo" :src="member.photo" :alt="`${member.first_name} ${member.last_name}`" />
                                    <AvatarFallback>{{ initials(member) }}</AvatarFallback>
                                </Avatar>
                            </TableCell>
                            <TableCell class="font-medium">
                                <TextLink :href="route('members.show', { member: member.id })">{{ displayName(member) }}</TextLink>
                            </TableCell>
                            <TableCell>
                                <div v-if="member.group_roles.length" class="flex flex-wrap gap-1.5">
                                    <Badge v-for="role in member.group_roles" :key="role" variant="secondary">{{
                                        trans(`group.role.${role}`)
                                    }}</Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">
                                <div v-if="hasContact(member)" class="flex flex-col">
                                    <span v-if="member.email !== undefined">{{ member.email }}</span>
                                    <span v-if="member.phone !== undefined">{{ member.phone }}</span>
                                </div>
                                <span v-else>{{ trans('group.roster.no_contact') }}</span>
                            </TableCell>
                            <TableCell><GroupStandingBadge :standing="member.group_standing" /></TableCell>
                            <TableCell v-if="canManage" class="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button type="button" variant="ghost" size="icon" class="size-8">
                                            <PhDotsThree class="size-4" />
                                            <span class="sr-only">{{ trans('group.roster.manage') }}</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @select="openManage(member)">{{ trans('group.roster.manage') }}</DropdownMenuItem>
                                        <DropdownMenuItem @select="resign(member)">{{ trans('group.roster.resign') }}</DropdownMenuItem>
                                        <template v-if="member.can_hard_remove">
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-destructive" @select="hardRemove(member)">{{
                                                trans('group.roster.remove')
                                            }}</DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>

                        <!-- Empty roster vs a search that matched nobody read as distinct states. -->
                        <TableRow v-if="!filteredMembers.length">
                            <TableCell :colspan="columnCount" class="text-muted-foreground py-10 text-center">
                                {{ members.length ? trans('group.roster.no_matches') : trans('group.roster.empty') }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- A–Z jump rail: a large-screen scanning aid that sticks beside the table. -->
            <AlphaJumpRail :available="availableLetters" class="sticky top-24 hidden self-start sm:flex" @jump="jumpTo" />
        </div>

        <!-- Add member dialog (#192) — search all Members, set standing, assign roles. -->
        <Dialog v-if="canManage" v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('group.roster.add_title') }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitAdd">
                    <div class="grid gap-2">
                        <Label for="add-search">{{ trans('group.roster.add_search') }}</Label>
                        <Input id="add-search" v-model="candidateSearch" type="search" :placeholder="trans('group.roster.search.placeholder')" />
                        <div class="max-h-48 overflow-y-auto border">
                            <button
                                v-for="candidate in candidates"
                                :key="candidate.id"
                                type="button"
                                class="hover:bg-muted flex w-full items-center px-3 py-2 text-left text-sm"
                                :class="addForm.member_id === candidate.id ? 'bg-muted font-medium' : ''"
                                @click="addForm.member_id = candidate.id"
                            >
                                {{ candidate.last_name }}, {{ candidate.first_name }}
                            </button>
                            <p v-if="!candidates.length" class="text-muted-foreground px-3 py-2 text-sm">{{ trans('group.roster.no_candidates') }}</p>
                        </div>
                        <p v-if="addForm.errors.member_id" class="text-destructive text-sm">{{ addForm.errors.member_id }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="add-standing">{{ trans('group.roster.field.standing') }}</Label>
                        <select id="add-standing" v-model="addForm.status" :class="SELECT_CLASS">
                            <option v-for="standing in SETTABLE_STANDINGS" :key="standing" :value="standing">
                                {{ trans(`group.standing.${standing}`) }}
                            </option>
                        </select>
                    </div>

                    <fieldset v-if="meta.assignableRoles.length" class="grid gap-2">
                        <legend class="text-muted-foreground mb-2 text-sm font-medium">{{ trans('group.roster.field.roles') }}</legend>
                        <label v-for="role in meta.assignableRoles" :key="role" class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="addForm.roles.includes(role)"
                                @update:checked="(on: boolean) => toggleRole(addForm.roles, role, on)"
                            />
                            {{ trans(`group.role.${role}`) }}
                        </label>
                    </fieldset>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="addForm.processing || addForm.member_id === null">{{
                            trans('group.roster.add_submit')
                        }}</Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="addForm.processing" @click="addOpen = false">
                            {{ trans('group.roster.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Manage membership dialog (#192) — standing, leave window, roles. -->
        <Dialog v-if="canManage" v-model:open="editOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ trans('group.roster.edit_title') }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitManage">
                    <div class="grid gap-2">
                        <Label for="edit-standing">{{ trans('group.roster.field.standing') }}</Label>
                        <select id="edit-standing" v-model="editForm.status" :class="SELECT_CLASS">
                            <option v-for="standing in SETTABLE_STANDINGS" :key="standing" :value="standing">
                                {{ trans(`group.standing.${standing}`) }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="edit-loa-start">{{ trans('group.roster.field.loa_start') }}</Label>
                            <Input id="edit-loa-start" v-model="editForm.loa_start" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-loa-end">{{ trans('group.roster.field.loa_end') }}</Label>
                            <Input id="edit-loa-end" v-model="editForm.loa_end" type="date" />
                        </div>
                    </div>
                    <p v-if="editForm.errors.loa_end" class="text-destructive text-sm">{{ editForm.errors.loa_end }}</p>

                    <fieldset v-if="meta.assignableRoles.length" class="grid gap-2">
                        <legend class="text-muted-foreground mb-2 text-sm font-medium">{{ trans('group.roster.field.roles') }}</legend>
                        <label v-for="role in meta.assignableRoles" :key="role" class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="editForm.roles.includes(role)"
                                @update:checked="(on: boolean) => toggleRole(editForm.roles, role, on)"
                            />
                            {{ trans(`group.role.${role}`) }}
                        </label>
                    </fieldset>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="editForm.processing">{{ trans('group.roster.save') }}</Button>
                        <Button type="button" variant="ghost" size="sm" :disabled="editForm.processing" @click="editMember = null">
                            {{ trans('group.roster.cancel') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
