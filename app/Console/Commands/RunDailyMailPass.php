<?php

namespace App\Console\Commands;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\ScheduleState;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\SignUp;
use App\Support\OrgTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * The daily pass (spec #479, ADR-0024 §7, §10) — the once-a-day, 06:00-org-time task that writes
 * the day's Reminder Deliveries. It **only writes rows**; the every-minute {@see DrainDeliveries}
 * sends them within the hour. Scheduled with a 10-minute overlap lock in `routes/console.php`.
 *
 * For each Group with Reminders on, it writes one Reminder for every Sign-up whose Shift starts
 * after now and before the end of today plus the Group's lead days, on a published Schedule, for
 * a Member without the no-email flag, **where no Reminder Delivery exists yet for that (Shift,
 * Member)**. That last predicate — enforced as a database fact by the unique (kind, shift_id,
 * member_id) grain and honoured here by {@see Delivery::firstOrCreate} — is what makes the pass a
 * window with a sent record rather than legacy's exact-date match: a missed day catches up for
 * free, and a seat dropped and re-taken by the same person stays one Reminder.
 *
 * The Delivery expires at the Shift's start (a Reminder past due is worthless), and a deleted
 * Shift cascades its Reminder away — so the Drain always renders a Reminder from a live Shift.
 */
class RunDailyMailPass extends Command
{
    protected $signature = 'mail:daily-pass';

    protected $description = 'Write the day’s Reminder Deliveries';

    public function handle(): int
    {
        $now = now();

        $groups = Group::query()
            ->where('reminders_enabled', true)
            ->where('has_scheduling', true)
            ->get();

        foreach ($groups as $group) {
            $this->writeReminders($group, $now);
        }

        return self::SUCCESS;
    }

    /**
     * Write the Reminders for one Group's window. The window end is the end of today plus the
     * Group's lead days on the org wall clock, converted to UTC so the comparison against the
     * UTC-stored `starts_at` never depends on the server zone.
     */
    private function writeReminders(Group $group, \DateTimeInterface $now): void
    {
        $windowEnd = OrgTime::now()->addDays($group->reminder_lead_days)->endOfDay()->utc();

        $signUps = SignUp::query()
            ->with(['shift', 'member'])
            ->whereHas('member', fn (Builder $member) => $member->where('no_email', false))
            ->whereHas('shift', fn (Builder $shift) => $shift
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $windowEnd)
                ->whereHas('schedule', fn (Builder $schedule) => $schedule
                    ->where('group_id', $group->getKey())
                    ->where('state', ScheduleState::Published)))
            ->get();

        foreach ($signUps as $signUp) {
            Delivery::firstOrCreate(
                [
                    'kind' => DeliveryKind::Reminder,
                    'shift_id' => $signUp->shift_id,
                    'member_id' => $signUp->member_id,
                ],
                [
                    'email' => $signUp->member->email,
                    'state' => DeliveryState::Pending,
                    'next_attempt_at' => $now,
                    'expires_at' => $signUp->shift->starts_at,
                ],
            );
        }
    }
}
