<?php

namespace App\Models;

use App\Http\Resources\MemberResource;
use Database\Factories\SignUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Sign-up (#357, PRD #352, ADR-0021 §Sign-up) — one Member on one Shift. Created either
 * by the Member (self-service) or by a Scheduler placing a named regular: one entity, two
 * actors, and the row carries no record of which (no provenance column — point 2 of #334).
 *
 * The (`shift_id`, `member_id`) pair is unique: a Member holds at most one seat on a Shift.
 * A Sign-up has nothing beyond its two foreign keys and timestamps — per-seat data (hours,
 * visitor counts) will hang here when the hours work lands, but the first pass exposes only
 * the Member's name, and only to viewers who can read the Schedule it sits on
 * ({@see MemberResource}, ADR-0017 §6).
 */
class SignUp extends Model
{
    /** @use HasFactory<SignUpFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shift_id',
        'member_id',
    ];

    /**
     * The Shift this Sign-up takes a seat on.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The Member holding the seat.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
