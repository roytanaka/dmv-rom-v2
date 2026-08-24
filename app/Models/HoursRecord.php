<?php

namespace App\Models;

use App\Support\OrgTime;
use Database\Factories\HoursRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * An Hours record (ADR-0022 §1) — one Member's hours in one Group for one calendar month,
 * at legacy's grain (Member, Group, month, optional Meeting). It carries `scheduled_hours`
 * and `extra_hours` as whole integers and a derived `total_hours` their sum.
 *
 * `total_hours` is **stored, not computed on read**, and kept in sync here in the model
 * rather than by a database trigger (§1) — the identity `total = scheduled + extra` is
 * brought into visible, testable code. Every write path that touches the two parts calls
 * {@see recomputeTotal()} before saving.
 *
 * The no-meeting sentinel (`meeting_id = 0`) lives in the migration; entry in this pass
 * always writes the sentinel, because meeting-hours entry is out of scope (§2).
 */
class HoursRecord extends Model
{
    /** @use HasFactory<HoursRecordFactory> */
    use HasFactory;

    /**
     * The sentinel `meeting_id` for a row that belongs to no Meeting. A real single value
     * (not SQL NULL) so the uniqueness grain holds for the ordinary no-meeting case — see
     * the migration's note.
     */
    public const NO_MEETING = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'member_id',
        'group_id',
        'year_month',
        'meeting_id',
        'scheduled_hours',
        'extra_hours',
        'total_hours',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'scheduled_hours' => 'integer',
            'extra_hours' => 'integer',
            'total_hours' => 'integer',
        ];
    }

    /**
     * The Member whose hours these are.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The Group the hours were spent helping.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The append-only provenance log for this record (ADR-0022 §6).
     *
     * @return HasMany<HoursAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(HoursAdjustment::class);
    }

    /**
     * Enter extra hours additively for a Member on a Group in one month, exactly as legacy
     * (ADR-0022 §2): the signed `$delta` is *added* to what is on file, a negative delta
     * corrects an overstatement, and the result floors at zero so a bad correction cannot
     * poison a report. The record is created on first touch. The whole write — the record
     * and its adjustment — is one transaction, so a record can never exist without its
     * trail (§6).
     *
     * A no-op is left with no trace (§16): a `$delta` of zero, or a negative one the floor
     * swallows entirely (e.g. -3 against 0 extra hours), writes neither the record nor an
     * adjustment and returns null — opening the dialog and closing it leaves nothing behind.
     * `$author` is always the authenticated writer; this method never reads it from input.
     *
     * The adjustment records the delta *actually applied* after the floor, so the log's
     * deltas always sum to the current `extra_hours`.
     */
    public static function enterExtra(Member $member, Group $group, string $yearMonth, int $delta, Member $author): ?self
    {
        return DB::transaction(function () use ($member, $group, $yearMonth, $delta, $author): ?self {
            $record = self::firstOrNew(
                [
                    'member_id' => $member->getKey(),
                    'group_id' => $group->getKey(),
                    'year_month' => $yearMonth,
                    'meeting_id' => self::NO_MEETING,
                ],
                ['scheduled_hours' => 0, 'extra_hours' => 0, 'total_hours' => 0],
            );

            $applied = max(0, $record->extra_hours + $delta) - $record->extra_hours;

            // Nothing changed — a zero entry, or a correction the floor swallowed. Leave no
            // record and no adjustment behind (§16).
            if ($applied === 0) {
                return null;
            }

            $record->extra_hours += $applied;
            $record->recomputeTotal();
            $record->save();

            $record->adjustments()->create([
                'delta' => $applied,
                'created_by' => $author->getKey(),
            ]);

            return $record;
        });
    }

    /**
     * Recalculate a Group's scheduled hours for one month from its Sign-ups (ADR-0022 §2) —
     * the derived half of the record, the opposite of {@see enterExtra()}. For every Member
     * holding a Sign-up on a **past** Shift on one of this Group's own Schedules in the given
     * month, the Shift durations are summed, the Group's `hours_multiplier` is applied once,
     * and the result **replaces** that Member's scheduled hours for the month. It never adds:
     * re-running is idempotent, so a closed month reads the same numbers twice.
     *
     * "Past" is ended-before-now on the org wall clock — a month still in progress counts only
     * the Shifts already worked, never the ones yet to come. Replace-not-add is honoured to its
     * conclusion: an existing row whose Sign-ups have since been removed is restated to zero,
     * not left stale.
     *
     * Two things it deliberately does not touch. **Extra hours** are a Member's own testimony
     * and survive every recalculation — only `scheduled_hours` is rewritten, and `total_hours`
     * is recomputed from the pair. And it appends **no Hours adjustment** (§6): the adjustment
     * log records human testimony, not derivation, so a derived write leaves no entry.
     *
     * Only the no-meeting bucket ({@see NO_MEETING}) carries scheduled hours, exactly as
     * legacy writes them at `subCommitteeID=0`; meeting rows carry extra hours only and are
     * left alone. The fiscal-year bound (§2, current year only) is enforced upstream at the
     * Form Request, not here — this method restates whatever month it is handed.
     *
     * Shift membership and past-ness are resolved in PHP against the org zone so neither the
     * month bucket nor the boundary depends on the database engine (date and zone handling
     * differ across engines — the sandbox note).
     */
    public static function recalculateScheduled(Group $group, string $yearMonth): void
    {
        $zone = config('app.org_timezone');
        $asOf = OrgTime::now();

        $shifts = Shift::query()
            ->whereHas('schedule', fn (Builder $query) => $query->where('group_id', $group->getKey()))
            ->where('ends_at', '<=', $asOf)
            ->with('signUps')
            ->get()
            ->filter(fn (Shift $shift) => $shift->starts_at->setTimezone($zone)->format('Ym') === $yearMonth);

        // Sum each Member's worked minutes across those Shifts, keyed by member id.
        $minutesByMember = [];
        foreach ($shifts as $shift) {
            $minutes = (int) $shift->starts_at->diffInMinutes($shift->ends_at);
            foreach ($shift->signUps as $signUp) {
                $minutesByMember[$signUp->member_id] = ($minutesByMember[$signUp->member_id] ?? 0) + $minutes;
            }
        }

        DB::transaction(function () use ($minutesByMember, $group, $yearMonth): void {
            // Every existing no-meeting row for the month, so replace-not-add can restate a
            // Member whose Sign-ups were removed down to zero rather than leaving it stale.
            $existing = self::query()
                ->where('group_id', $group->getKey())
                ->where('year_month', $yearMonth)
                ->where('meeting_id', self::NO_MEETING)
                ->get()
                ->keyBy('member_id');

            $memberIds = collect($minutesByMember)->keys()
                ->merge($existing->keys())
                ->unique();

            foreach ($memberIds as $memberId) {
                // The multiplier applies once, to the summed minutes, before the whole-hour
                // conversion — Walker's `2 *` moved from PHP into data (§7).
                $hours = (int) round((($minutesByMember[$memberId] ?? 0) * $group->hours_multiplier) / 60);
                $record = $existing->get($memberId);

                // A Member with neither an existing row nor any derived hours needs no row.
                if ($record === null && $hours === 0) {
                    continue;
                }

                $record ??= self::make([
                    'member_id' => $memberId,
                    'group_id' => $group->getKey(),
                    'year_month' => $yearMonth,
                    'meeting_id' => self::NO_MEETING,
                    'extra_hours' => 0,
                ]);

                $record->scheduled_hours = $hours;
                $record->recomputeTotal();
                $record->save();
            }
        });
    }

    /**
     * Keep the stored `total_hours` equal to `scheduled_hours + extra_hours` — the identity
     * legacy expresses in a database trigger, brought into the model (ADR-0022 §1). Called
     * by every write path before saving.
     */
    public function recomputeTotal(): void
    {
        $this->total_hours = $this->scheduled_hours + $this->extra_hours;
    }
}
