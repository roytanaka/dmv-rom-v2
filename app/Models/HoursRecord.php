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
        'extra_interactions',
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
            'extra_interactions' => 'integer',
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
        return self::enterExtras($member, $group, $yearMonth, $delta, 0, $author);
    }

    /**
     * Enter extra hours and extra interactions together in one write (ADR-0023 §6, amending
     * ADR-0022 §2). Extra interactions are visitors a Member served outside any Shift — the
     * hand-typed route into the department's headline number for the six Groups without a
     * scheduling one. Each part behaves exactly as extra hours always have: the signed delta
     * is *added* to what is on file, a negative delta corrects, and the result floors at zero.
     *
     * The two parts share one record and one transaction, so entering hours and interactions
     * in one submit is a single atomic write and a no-op on both leaves nothing behind (§16) —
     * neither the record nor either adjustment. Each part that actually moves appends its own
     * {@see HoursAdjustment} carrying the applied delta, its author, and which field it moved,
     * so a mistyped interaction count is as correctable and as attributable as a mistyped hour.
     *
     * Extra interactions are **outside `total_hours`**: {@see recomputeTotal()} sums only the
     * two hours parts, so an interactions write never touches an hours figure. A visitor count
     * is not time worked. `$author` is always the authenticated writer; this never reads it
     * from input.
     */
    public static function enterExtras(Member $member, Group $group, string $yearMonth, int $hoursDelta, int $interactionsDelta, Member $author): ?self
    {
        return DB::transaction(function () use ($member, $group, $yearMonth, $hoursDelta, $interactionsDelta, $author): ?self {
            $record = self::firstOrNew(
                [
                    'member_id' => $member->getKey(),
                    'group_id' => $group->getKey(),
                    'year_month' => $yearMonth,
                    'meeting_id' => self::NO_MEETING,
                ],
                ['scheduled_hours' => 0, 'extra_hours' => 0, 'total_hours' => 0, 'extra_interactions' => 0],
            );

            // Each part floors at zero: the change actually applied is what a negative correction
            // can subtract without driving the running total below zero.
            $appliedHours = max(0, $record->extra_hours + $hoursDelta) - $record->extra_hours;
            $appliedInteractions = max(0, $record->extra_interactions + $interactionsDelta) - $record->extra_interactions;

            // Nothing changed on either part — a zero/blank entry, or a correction the floor
            // swallowed. Leave no record and no adjustment behind (§16).
            if ($appliedHours === 0 && $appliedInteractions === 0) {
                return null;
            }

            $record->extra_hours += $appliedHours;
            $record->extra_interactions += $appliedInteractions;
            $record->recomputeTotal();
            $record->save();

            // One adjustment per part that actually moved, each naming its field so the log's
            // deltas sum to the current value of that field.
            if ($appliedHours !== 0) {
                $record->adjustments()->create([
                    'field' => HoursAdjustment::FIELD_EXTRA_HOURS,
                    'delta' => $appliedHours,
                    'created_by' => $author->getKey(),
                ]);
            }

            if ($appliedInteractions !== 0) {
                $record->adjustments()->create([
                    'field' => HoursAdjustment::FIELD_EXTRA_INTERACTIONS,
                    'delta' => $appliedInteractions,
                    'created_by' => $author->getKey(),
                ]);
            }

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
     * A Shift falls in the month of its **`ends_at`** (ADR-0023 §4) — the one Shift crossing
     * midnight into a new month is credited to the month it finished in. Shift membership,
     * past-ness and the month bucket are resolved in PHP against the org zone so none of them
     * depends on the database engine (date and zone handling differ across engines — the
     * sandbox note).
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
            ->filter(fn (Shift $shift) => $shift->ends_at->setTimezone($zone)->format('Ym') === $yearMonth);

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
                // conversion — the factor lives in data, not hardcoded in PHP (ADR-0022 §7).
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
