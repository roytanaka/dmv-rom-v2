<?php

namespace App\Enums;

/**
 * An org-wide system function a Group stewards (PRD #126, slice 5). A Group that
 * stewards a function operates a service the rest of the DMV depends on — the
 * Records Group stewards `member_admin`, so member-administration authority is
 * membership in that Group, not a standalone flag (ADR-0011: authority stays
 * explicit and per-Group).
 */
enum StewardshipFunction: string
{
    case MemberAdmin = 'member_admin';
    case Statistics = 'statistics';
    case Website = 'website';

    // Intake (ADR-0038 §2): the Group that stewards `applicants` runs the Applicants — the
    // Membership Group today, as the Membership subcommittee ran intake in the Legacy app.
    // Exactly one Group holds it. Its members have applicant authority
    // ({@see \App\Models\Member::hasApplicantAuthority()}); its Chair is the Applicant Chair
    // ({@see \App\Models\Member::isApplicantChair()}).
    case Applicants = 'applicants';

    // The org-wide mail franchise (ADR-0024 §5): a Group that stewards `org_mail`
    // may send the org-wide Broadcast Audiences — All Members, One Category, the
    // Directory hand-pick — that reach past any one Group's roster. Seeded on the
    // DMV Executive, Records, and Awards Groups. Membership in any stewarding Group
    // is one of the two ways a Member is an "org-wide sender"
    // ({@see \App\Models\Member::isOrgWideSender()}); the other is a Chair role.
    case OrgMail = 'org_mail';

    /**
     * Whether exactly one Group holds this stewardship: `Group::stewardOf()` reads one holder,
     * and leaving it empty would leave nobody holding the authority (ADR-0036 §7).
     */
    public function hasSingleHolder(): bool
    {
        return in_array($this, [self::MemberAdmin, self::Applicants], true);
    }
}
