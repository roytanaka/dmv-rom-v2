<?php

namespace App\Enums;

/**
 * The surface a composer's Audience menu is opened from (ADR-0024 §6). Every
 * Broadcast/Direct-message Audience is resolved *for a context*: the same
 * Audience key means different people in different places, and which Audiences
 * an actor may pick at all depends on where they are. The five contexts mirror
 * the six compose entry points (the roster tab shares the Group context).
 *
 * Backed string enum: the stored value is the request's `context` parameter.
 */
enum ContextType: string
{
    // A Group page (and its roster tab): the Group's own roster and officer
    // Audiences, resolved from that Group.
    case Group = 'group';

    // An opened Schedule: the Sign-ups-on-this-Schedule Audience, plus the
    // owning Group's Audiences.
    case Schedule = 'schedule';

    // A single Shift: the Sign-ups-on-this-Shift Audience alone.
    case Shift = 'shift';

    // The org-wide Directory: the org-wide Broadcast Audiences and the three
    // leadership Audiences — never scoped to any one Group.
    case Directory = 'directory';

    // A Member's profile: the one-Member Direct-message Audience.
    case Member = 'member';

    // One Invitation (ADR-0034 §7): its Guests, narrowed by the Audience key. Only its Host
    // and the super-tier may pick here.
    case Invitation = 'invitation';

    // A Records office Member list (ADR-0038 §5), named by its list word: the whole list.
    // Member administration only.
    case MemberList = 'member_list';

    // A Saved query run (ADR-0038 §7), named by the query id: the run's Members, the
    // unchecked rows removed. Member administration only.
    case SavedQuery = 'saved_query';

    // The Applicants list (#929, ADR-0038 §8), named by its status filter (`all` or one Applicant
    // status): the filtered Applicants. Holder members of the `applicants` Stewardship only.
    case Applicants = 'applicants';
}
