<?php

namespace App\Console\Commands;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\Reply;
use App\Enums\ScheduleState;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Guest;
use App\Models\SignUp;
use App\Support\EmptyDesk\EmptyDeskAlertWriter;
use App\Support\OrgTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The daily pass (spec #479, ADR-0024 §7, §10) — the once-a-day, 06:00-org-time task that writes
 * the day's Reminder and empty-desk-alert Deliveries. It **only writes rows**; the every-minute
 * {@see DrainDeliveries} sends them within the hour. Scheduled with a 10-minute overlap lock in
 * `routes/console.php`.
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
 *
 * The Beta mail-off switch (`mail.daily_pass_off`, ADR-0035 §6) is read once at the top: when on,
 * the pass writes nothing and logs one line. Every writer added here sits below that check.
 */
class RunDailyMailPass extends Command
{
    protected $signature = 'mail:daily-pass';

    protected $description = 'Write the day’s Reminder and empty-desk-alert Deliveries';

    /** How many days ahead of the occasion an Invitation Reminder goes (ADR-0034 §12). */
    private const INVITATION_REMINDER_LEAD_DAYS = 4;

    public function handle(EmptyDeskAlertWriter $emptyDeskAlerts): int
    {
        if (config('mail.daily_pass_off')) {
            Log::info('mail:daily-pass skipped: MAIL_DAILY_PASS_OFF is on (Beta), so no Deliveries were written.');

            return self::SUCCESS;
        }

        $now = now();

        $groups = Group::query()
            ->where('reminders_enabled', true)
            ->where('has_scheduling', true)
            ->get();

        foreach ($groups as $group) {
            $this->writeReminders($group, $now);
        }

        $this->writeInvitationReminders($now);

        // The empty-desk alert runs on its own every-third-day cadence and Group-setting gate,
        // so it is its own writer (ADR-0024 §7); the pass just hands it the wheel.
        $emptyDeskAlerts->write();

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

    /**
     * Write the Invitation Reminders (#909, ADR-0034 §12), the same window with a sent record
     * on the (Invitation, Member) grain. The lead is fixed, because no Group owns the setting.
     * A Guest who is no longer current stays on the list but gets no more mail (spec #901).
     *
     * The row is still the sent record, so a Reminder that went out is never written again. An
     * Expired row never went out: its Guest declined or the Invitation went back to Draft, and
     * the Drain ended it. A Guest who qualifies again gets that same row back, on a fresh expiry.
     */
    private function writeInvitationReminders(\DateTimeInterface $now): void
    {
        $lastDay = OrgTime::today()->addDays(self::INVITATION_REMINDER_LEAD_DAYS);

        $guests = Guest::query()
            ->with(['invitation', 'member'])
            ->where('reply', Reply::Accepted)
            ->whereHas('member', fn (Builder $member) => $member->inDirectory()->where('no_email', false))
            ->whereHas('invitation', fn (Builder $invitation) => $invitation
                ->takingReplies()
                ->whereDate('date', '<=', $lastDay->toDateString()))
            ->get();

        foreach ($guests as $guest) {
            $startsAt = $guest->invitation->startsAt();

            if ($startsAt->lte($now)) {
                continue;
            }

            $queued = [
                'email' => $guest->member->email,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => $now,
                'expires_at' => $startsAt,
            ];

            $row = Delivery::firstOrCreate(
                [
                    'kind' => DeliveryKind::InvitationReminder,
                    'invitation_id' => $guest->invitation_id,
                    'member_id' => $guest->member_id,
                ],
                $queued,
            );

            if ($row->state === DeliveryState::Expired && $row->sent_at === null) {
                $row->update($queued);
            }
        }
    }
}
