<?php

namespace App\Support\Audiences;

use App\Enums\ContextType;
use App\Enums\MemberList;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\SavedQuery;
use App\Models\Schedule;
use App\Models\Shift;
use App\Support\Applicants\ApplicantFilter;

/**
 * The surface an Audience is being picked from (ADR-0024 §6) — a {@see ContextType}
 * and, for every context but the Directory, the subject it is scoped to. The
 * resolver reads this to decide which Audiences are on offer and to resolve them:
 * a Group's roster, a Schedule's Sign-ups, one Member for a Direct message.
 */
final class AudienceContext
{
    private function __construct(
        public readonly ContextType $type,
        public readonly Group|Schedule|Shift|Member|Invitation|MemberList|SavedQuery|ApplicantFilter|null $subject,
    ) {}

    public static function memberList(MemberList $list): self
    {
        return new self(ContextType::MemberList, $list);
    }

    public static function savedQuery(SavedQuery $query): self
    {
        return new self(ContextType::SavedQuery, $query);
    }

    /** The Applicants list under its filter. */
    public static function applicants(ApplicantFilter $filter): self
    {
        return new self(ContextType::Applicants, $filter);
    }

    public static function group(Group $group): self
    {
        return new self(ContextType::Group, $group);
    }

    public static function schedule(Schedule $schedule): self
    {
        return new self(ContextType::Schedule, $schedule);
    }

    public static function shift(Shift $shift): self
    {
        return new self(ContextType::Shift, $shift);
    }

    public static function directory(): self
    {
        return new self(ContextType::Directory, null);
    }

    public static function member(Member $member): self
    {
        return new self(ContextType::Member, $member);
    }

    public static function invitation(Invitation $invitation): self
    {
        return new self(ContextType::Invitation, $invitation);
    }

    /**
     * The Group this context is scoped to, resolving a Schedule and a Shift up to
     * their owning Group — the Group whose roster and officer Audiences the Schedule
     * context also offers (ADR-0024 §6.3). Null for the Directory, a Member profile,
     * an Invitation, and the Records office's Member lists and Saved queries, which are not scoped to any one Group.
     */
    public function owningGroup(): ?Group
    {
        return match ($this->type) {
            ContextType::Group => $this->subject,
            ContextType::Schedule => $this->subject->group,
            ContextType::Shift => $this->subject->schedule->group,
            ContextType::Directory, ContextType::Member, ContextType::Invitation,
            ContextType::MemberList, ContextType::SavedQuery, ContextType::Applicants => null,
        };
    }
}
