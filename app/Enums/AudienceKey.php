<?php

namespace App\Enums;

/**
 * The closed catalog of Broadcast/Direct-message Audiences (ADR-0024 §5) — the
 * named recipient sets an officer or Member may pick, each resolved on the server
 * at send time from the Group model. The browser names the key; it never posts a
 * recipient list. Several keys are parameterised (the parameter travels beside
 * the key): {@see OneStatus} by a {@see MembershipStatus}, {@see OneCategory} by a
 * {@see Category}, {@see ChildRoster} by a child Group slug, and {@see OneMember}
 * by a Member id.
 *
 * Backed string enum: the stored value is the request's `audience` parameter and
 * the key the sent record keeps.
 */
enum AudienceKey: string
{
    // Group-context roster Audiences — pickable by any member of the Group.
    case WholeGroup = 'whole_group';
    case WholeGroupOnLeave = 'whole_group_on_leave';
    case OneStatus = 'one_status';
    case GroupActive = 'group_active';
    case HandPicked = 'hand_picked';

    // Group-context officer Audiences — pickable by the Group's officers.
    case GroupOfficers = 'group_officers';
    case ChildRoster = 'child_roster';

    // Scheduling Audiences — pickable by a Chair or Scheduler.
    case SignUpsSchedule = 'sign_ups_schedule';
    case SignUpsShift = 'sign_ups_shift';

    // Org-wide Audiences — pickable by an org-wide sender, on the Directory.
    case AllMembers = 'all_members';
    case AllMembersOnLeave = 'all_members_on_leave';
    case ActiveProvisional = 'active_provisional';
    case OneCategory = 'one_category';

    // Leadership Audiences — pickable by any Member, on the Directory.
    case BoardOfDirectors = 'board_of_directors';
    case CommitteeChairs = 'committee_chairs';
    case AllChairs = 'all_chairs';

    // Direct message — pickable by any Member, from a profile.
    case OneMember = 'one_member';

    /**
     * Whether this Audience carries a parameter beside its key (the chosen status,
     * category, child Group, or Member). The resolver requires the parameter for
     * these and rejects it for the rest.
     */
    public function isParameterised(): bool
    {
        return match ($this) {
            self::OneStatus, self::OneCategory, self::ChildRoster, self::OneMember => true,
            default => false,
        };
    }
}
