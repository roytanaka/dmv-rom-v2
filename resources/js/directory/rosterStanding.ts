/**
 * The roster's DMV-wide standing marker (#877). A Group roster shows each Member's standing
 * in that Group; a viewer who manages the roster also sees the Member's DMV-wide standing
 * (Category) when it is not Active — the legacy "DMV Status" column, Chair and Secretary only.
 * The two standings stay separate: a Category change never touches the Group standing.
 *
 * Pure over the delivered row: `standing` already rides on it (`MemberResource`), and the
 * manager hint is the server's `can.manageRoster`, off on the root DMV Group.
 */

/** The DMV-wide standing to mark on the row, or null when there is nothing to mark. */
export function dmvStandingMarker(standing: string, canManage: boolean): string | null {
    return canManage && standing !== 'active' ? standing : null;
}
