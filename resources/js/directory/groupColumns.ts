/**
 * The Directory's Group-dependent columns (#700). With a Group picked, each row shows the
 * Member's roles in that Group, and the Groups column lists only the other Groups. With
 * "All Groups" (an empty slug), the Groups column lists every Group.
 *
 * Pure over the delivered row: each membership already carries its roles
 * (`MemberResource` `groups[].roles`), so nothing here reaches the server.
 */

/** The slice of a Directory membership the columns read. */
export interface DirectoryMembership {
    name: string;
    slug: string;
    roles: string[];
}

/** The Member's role values in the picked Group, or none when no Group is picked. */
export function rolesIn(groups: DirectoryMembership[], slug: string): string[] {
    if (slug === '') return [];

    return groups.find((group) => group.slug === slug)?.roles ?? [];
}

/** The Groups to list in the row: every Group, or all but the picked one. */
export function otherGroups<T extends DirectoryMembership>(groups: T[], slug: string): T[] {
    return slug === '' ? groups : groups.filter((group) => group.slug !== slug);
}
