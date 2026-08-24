<?php

namespace App\Models;

use Database\Factories\HoursRecordFactory;
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
     * Keep the stored `total_hours` equal to `scheduled_hours + extra_hours` — the identity
     * legacy expresses in a database trigger, brought into the model (ADR-0022 §1). Called
     * by every write path before saving.
     */
    public function recomputeTotal(): void
    {
        $this->total_hours = $this->scheduled_hours + $this->extra_hours;
    }
}
