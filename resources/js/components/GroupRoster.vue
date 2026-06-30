<script setup lang="ts">
// Group Roster tab (#189, PRD #186) — the Group-scoped instance of the Directory
// surface. Reuses the Directory's row presentation (avatar, name linking through to
// the profile, contact gated by `viewContact`) plus its name filter and A–Z jump
// rail, so a member doesn't learn a new surface. Two Group-specific additions per
// row: the role badge(s) the member holds in this Group, and a standing badge shown
// only when the within-Group standing isn't Full.
//
// Ordering is pure A–Z by surname (officers are surfaced on the Overview, not floated
// here). The payload arrives pre-sorted from the server; the rail re-derives its
// letter buckets from the same surname so the two can never disagree. Contact PII is
// present in a row only when the server deemed the viewer authorized — this surface
// renders what it's given and computes no authority.
import AlphaJumpRail from '@/components/AlphaJumpRail.vue';
import GroupStandingBadge from '@/components/GroupStandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type RosterMember } from '@/types';
import { PhMagnifyingGlass } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ members: RosterMember[] }>();

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
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="relative sm:max-w-xs">
            <PhMagnifyingGlass class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input
                v-model="search"
                type="search"
                :placeholder="trans('group.roster.search.placeholder')"
                :aria-label="trans('group.roster.search.label')"
                class="pl-9"
            />
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
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="member in filteredMembers" :id="anchorId(member)" :key="member.id" class="scroll-mt-24">
                            <TableCell>
                                <Avatar size="sm">
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
                        </TableRow>

                        <!-- Empty roster vs a search that matched nobody read as distinct states. -->
                        <TableRow v-if="!filteredMembers.length">
                            <TableCell colspan="5" class="text-muted-foreground py-10 text-center">
                                {{ members.length ? trans('group.roster.no_matches') : trans('group.roster.empty') }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- A–Z jump rail: a large-screen scanning aid that sticks beside the table. -->
            <AlphaJumpRail :available="availableLetters" class="sticky top-24 hidden self-start sm:flex" @jump="jumpTo" />
        </div>
    </div>
</template>
