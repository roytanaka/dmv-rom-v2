<?php

namespace App\Support\Audiences;

use App\Enums\ContextType;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;

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
        public readonly Group|Schedule|Shift|Member|null $subject,
    ) {}

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

    /**
     * The Group this context is scoped to, resolving a Schedule and a Shift up to
     * their owning Group — the Group whose roster and officer Audiences the Schedule
     * context also offers (ADR-0024 §6.3). Null for the Directory and a Member
     * profile, which are not scoped to any one Group.
     */
    public function owningGroup(): ?Group
    {
        return match ($this->type) {
            ContextType::Group => $this->subject,
            ContextType::Schedule => $this->subject->group,
            ContextType::Shift => $this->subject->schedule->group,
            ContextType::Directory, ContextType::Member => null,
        };
    }
}
