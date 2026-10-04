<?php

namespace App\Support\EmptyDesk;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\ScheduleState;
use App\Mail\EmptyDeskAlert;
use App\Models\Delivery;
use App\Models\EmptyDeskRun;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The empty-desk alert writer (#487, spec #479, ADR-0024 §7). Part of the daily pass: on days of
 * the month divisible by 3 — legacy's odd but faithfully ported cadence — it tells each Group's
 * roster which watched Shifts still have nobody. It **only writes rows** ({@see EmptyDeskRun} and
 * {@see Delivery}); the every-minute Drain sends the mail within the hour.
 *
 * For each Group with the alert on and no run row for today, it collects the Shifts on published
 * Schedules from the start of today through the end of today plus the Group's look-ahead whose
 * kind is watched ({@see ShiftKind::$alert_when_empty}) and which have zero Sign-ups —
 * a vacancy, not a Shift merely below capacity. A run row carrying the count is written even on a
 * silent day, so the unique (Group, date) grain makes a second pass the same day a no-op and a
 * missed cadence day is not caught up. When any Shift is open, one {@see DeliveryKind::EmptyDesk}
 * Delivery goes to every roster Member in present standing without the no-email flag, with the
 * open-Shift list frozen into the payload — the Shifts may be filled or gone by drain time, so
 * the mail renders from the snapshot, not live rows.
 */
class EmptyDeskAlertWriter
{
    /** The cadence: the alert fires only on days of the month divisible by this constant. */
    private const CADENCE = 3;

    /**
     * Write today's empty-desk rows, or nothing when today is not a cadence day.
     */
    public function write(): void
    {
        $today = OrgTime::now();

        if ($today->day % self::CADENCE !== 0) {
            return;
        }

        $runDate = $today->toDateString();

        $groups = Group::query()
            ->where('empty_desk_alert_enabled', true)
            ->where('has_scheduling', true)
            ->whereDoesntHave('emptyDeskRuns', fn (Builder $run) => $run->whereDate('run_date', $runDate))
            ->get();

        foreach ($groups as $group) {
            $this->writeForGroup($group, $today);
        }
    }

    /**
     * Write one Group's run row and, if any Shift is open, its Deliveries. The run row is written
     * whatever the count, so a silent day is recorded and never revisited.
     */
    private function writeForGroup(Group $group, CarbonImmutable $today): void
    {
        $openShifts = $this->openWatchedShifts($group, $today);

        EmptyDeskRun::create([
            'group_id' => $group->getKey(),
            'run_date' => $today->toDateString(),
            'open_shift_count' => $openShifts->count(),
        ]);

        if ($openShifts->isEmpty()) {
            return;
        }

        $payload = EmptyDeskAlert::snapshot($group, $openShifts, $today);

        foreach ($this->recipients($group) as $member) {
            Delivery::create([
                'kind' => DeliveryKind::EmptyDesk,
                'member_id' => $member->getKey(),
                'email' => $member->email,
                'payload' => $payload,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]);
        }
    }

    /**
     * The unstaffed watched Shifts in the Group's window: on a published Schedule of the Group,
     * of a watched kind, starting from the start of today through the end of today plus the
     * look-ahead on the org wall clock, and with zero Sign-ups. Ordered by start so the mail
     * reads chronologically.
     *
     * @return Collection<int, Shift>
     */
    private function openWatchedShifts(Group $group, CarbonImmutable $today): Collection
    {
        $windowStart = $today->startOfDay()->utc();
        $windowEnd = $today->addDays($group->empty_desk_days_ahead)->endOfDay()->utc();

        return Shift::query()
            ->with('kind')
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->whereHas('kind', fn (Builder $kind) => $kind->where('alert_when_empty', true))
            ->whereHas('schedule', fn (Builder $schedule) => $schedule
                ->where('group_id', $group->getKey())
                ->where('state', ScheduleState::Published))
            ->whereDoesntHave('signUps')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The Group's roster in present standing without the no-email flag — the recipients (§7, §9).
     * Present standing is the sign-up floor ({@see MembershipStatus::canSignUp}): a
     * vacancy alert to someone who cannot fill it is noise. The flag is checked once, here, the
     * sole point a Delivery is written.
     *
     * @return Collection<int, Member>
     */
    private function recipients(Group $group): Collection
    {
        return $group->memberships()
            ->with('member')
            ->get()
            ->filter(fn (GroupMember $membership): bool => $membership->status->canSignUp())
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->reject(fn (Member $member): bool => (bool) $member->no_email)
            ->unique(fn (Member $member): int => $member->getKey())
            ->values();
    }
}
