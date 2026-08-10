<?php

namespace App\Models;

use App\Enums\ShiftAudience;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Shift (#355, PRD #352, ADR-0021 §2) — a slot on a Schedule. A Shift is a *slot, not
 * a seat*: `capacity` is an integer, never N byte-identical rows. It belongs to exactly
 * one Schedule (there are no orphan Shifts — a one-off Shift is a Schedule with a
 * one-day range) and carries an optional {@see ShiftKind}; Reception's Shifts carry
 * null.
 *
 * A Shift has **no state** — it inherits everything (visibility, past-ness, its Group)
 * from its Schedule. `starts_at` / `ends_at` are instants stored in UTC and read on the
 * org wall clock; duration is always derived from the pair, never stored. `audience`
 * decides who is shown a Sign-up button ({@see ShiftAudience}); it does not bind a
 * Scheduler placing a named person.
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'schedule_id',
        'starts_at',
        'ends_at',
        'capacity',
        'shift_kind_id',
        'audience',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'audience' => ShiftAudience::class,
        ];
    }

    /**
     * The Schedule this Shift belongs to — the source of everything it inherits.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * The Shift's kind, where the Group uses kinds. Null for Reception's Shifts.
     *
     * @return BelongsTo<ShiftKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(ShiftKind::class, 'shift_kind_id');
    }

    /**
     * The Sign-ups on this Shift — the Members holding its seats (#357, ADR-0021). The
     * Shift is full when this count reaches {@see $capacity}; lowering capacity below it
     * is blocked, and deleting the Shift is permitted only when it is empty.
     *
     * @return HasMany<SignUp, $this>
     */
    public function signUps(): HasMany
    {
        return $this->hasMany(SignUp::class);
    }
}
