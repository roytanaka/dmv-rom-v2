// PROTOTYPE (#467) — builds a ComposeContext from each host page's own props, so the
// picker shows real names at real density. Audience definitions follow the audience
// research (#462): Whole Group = present standing, Active = Full, officers = every Role,
// Sign-ups on a Shift / across a Schedule, org-wide sets are Records-only.
import type { RosterMember, ScheduleDetail, ShiftAgendaItem } from '@/types';
import { dedupe, type Audience, type ComposeContext, type Person } from './model';

const PRESENT = new Set(['full', 'trainee', 'transitional', 'auxiliary', 'projects', 'emeritus', 'donor']);

const fromRoster = (m: RosterMember): Person => ({
    id: m.id,
    first_name: m.first_name,
    last_name: m.last_name,
    photo: m.photo,
    hint: m.group_roles.length ? m.group_roles.join(', ') : m.group_standing,
});

export const groupContext = (group: { name: string }, roster: RosterMember[], isOfficer: boolean): ComposeContext => {
    const people = roster.map(fromRoster);
    const present = roster.filter((m) => PRESENT.has(m.group_standing)).map(fromRoster);
    const audiences: Audience[] = [
        { key: 'whole', name: 'Whole Group', members: present },
        { key: 'active', name: 'Active members', members: roster.filter((m) => m.group_standing === 'full').map(fromRoster) },
    ];
    if (isOfficer) {
        audiences.push({ key: 'officers', name: 'Officers', members: roster.filter((m) => m.group_roles.length).map(fromRoster) });
    }
    return { kind: 'group', title: group.name, fromName: group.name, audiences, roster: people };
};

const fromSignUp = (s: { id: number; first_name: string; last_name: string }, hint: string): Person => ({
    id: s.id,
    first_name: s.first_name,
    last_name: s.last_name,
    hint,
});

export const scheduleContext = (group: { name: string }, schedule: ScheduleDetail, roster: RosterMember[], isOfficer: boolean): ComposeContext => {
    const now = Date.now();
    let upcoming = schedule.shifts.filter((s) => new Date(s.ends_at).getTime() >= now);
    // Demo data may be all in the past; fall back to every Shift so the picker has names.
    const allPast = upcoming.length === 0;
    if (allPast) upcoming = schedule.shifts;
    const signedUp = dedupe(upcoming.flatMap((s) => s.signups.map((x) => fromSignUp(x, 'signed up'))));
    const base = groupContext(group, roster, isOfficer);
    return {
        kind: 'schedule',
        title: schedule.name,
        fromName: group.name,
        audiences: [
            {
                key: 'schedule',
                name: `Sign-ups on ${schedule.name}`,
                members: signedUp,
                note: allPast ? 'all Shifts (none upcoming)' : 'upcoming Shifts only',
            },
            ...base.audiences,
        ],
        roster: base.roster,
    };
};

export const shiftContext = (group: { name: string }, shift: ShiftAgendaItem, label: string, roster: RosterMember[]): ComposeContext => {
    const signedUp = shift.signups.map((x) => fromSignUp(x, 'signed up'));
    return {
        kind: 'shift',
        title: label,
        fromName: group.name,
        audiences: [{ key: 'shift', name: `Sign-ups on this Shift`, members: signedUp }],
        roster: roster.map(fromRoster),
    };
};

interface DirectoryMember {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    standing: string;
    groups: { name: string; slug: string; roles: string[] }[];
}

export const directoryContext = (members: DirectoryMember[]): ComposeContext => {
    const people: Person[] = members.map((m) => ({
        id: m.id,
        first_name: m.first_name,
        last_name: m.last_name,
        photo: m.photo,
        hint: m.groups.map((g) => g.name).join(', '),
    }));
    const byStanding = (s: string) => members.filter((m) => m.standing === s).map((m) => people.find((p) => p.id === m.id)!);
    const chairs = members.filter((m) => m.groups.some((g) => g.roles.some((r) => /chair/i.test(r)))).map((m) => people.find((p) => p.id === m.id)!);
    const board = members.filter((m) => m.groups.some((g) => /executive/i.test(g.name))).map((m) => people.find((p) => p.id === m.id)!);
    const notLoa = members.filter((m) => m.standing !== 'loa').map((m) => people.find((p) => p.id === m.id)!);
    return {
        kind: 'directory',
        title: 'Directory',
        fromName: 'DMV Records',
        audiences: [
            { key: 'all', name: 'All Members', members: notLoa, note: 'Records only · excludes leave' },
            { key: 'all_loa', name: 'All Members and on leave', members: people, note: 'Records only' },
            { key: 'active', name: 'Active', members: byStanding('active'), note: 'Records only' },
            { key: 'board', name: 'Board of Directors', members: board, note: 'Records only' },
            { key: 'chairs', name: 'Committee Chairs', members: chairs, note: 'Records only' },
        ],
        roster: people,
    };
};

export const memberContext = (
    member: { id: number; first_name: string; last_name: string; photo: string | null },
    senderName: string,
): ComposeContext => ({
    kind: 'member',
    title: `${member.first_name} ${member.last_name}`,
    fromName: senderName,
    audiences: [],
    roster: [],
    fixed: { id: member.id, first_name: member.first_name, last_name: member.last_name, photo: member.photo },
});
