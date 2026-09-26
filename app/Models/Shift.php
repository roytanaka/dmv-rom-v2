<?php

namespace App\Models;

use App\Enums\ShiftAudience;
use App\Support\Scheduling\ObjectHold;
use Carbon\CarbonImmutable;
use Database\Factories\ShiftFactory;
use DateTimeInterface;
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
     * The most units a self-serve Shift may run (#585, ADR-0026 §2). A hard constant, not a
     * Group setting: legacy's dropdown bound of 8 carried no meaning behind it, so it stays a
     * ceiling the Form Requests enforce rather than a per-Group knob.
     */
    public const SELF_SERVE_MAX_UNITS = 8;

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
     * The end instant a self-serve Shift derives from its start, a unit count, and the
     * Group's per-unit length (#585, ADR-0026 §2). A Gallery Interpreter picks units, not
     * an end time — the count is never stored — so `ends_at` is always `starts_at` plus
     * `units` × `unitMinutes`. The single server-side home of that arithmetic, mirroring
     * the client's `deriveEndsAt` helper so the form's preview and the stored value agree.
     */
    public static function deriveEndsAt(DateTimeInterface $startsAt, int $units, int $unitMinutes): CarbonImmutable
    {
        return CarbonImmutable::instance($startsAt)->addMinutes($units * $unitMinutes);
    }

    /**
     * The window an Object on this Shift's seats counts as in use (#586, ADR-0026 §3, §4) — the
     * single home of that arithmetic, read by the double-booking block. For an ordinary Shift it
     * is the Shift's own `[starts_at, ends_at]`; an off-site kind widens it to the start of the
     * day before and the end of the day after (#587), which lands here as one added branch.
     */
    public function objectHold(): ObjectHold
    {
        $start = CarbonImmutable::instance($this->starts_at);
        $end = CarbonImmutable::instance($this->ends_at);

        if ($this->kind?->off_site) {
            $timezone = config('app.org_timezone');
            $start = $start->setTimezone($timezone)->startOfDay()->subDay();
            $end = $end->setTimezone($timezone)->endOfDay()->addDay();
        }

        return new ObjectHold($start, $end);
    }

    /**
     * Whether the Shift's start instant has passed on the org wall clock (#554, ADR-0021
     * §Sign-up) — a seat is takeable and droppable by a Member only *until* the Shift
     * starts. The comparison is instant-to-instant, so it is timezone-agnostic; there is
     * no lead window, because the ADR has none.
     */
    public function hasStarted(): bool
    {
        return ! CarbonImmutable::now()->isBefore($this->starts_at);
    }

    /**
     * Whether the Member may change or delete this self-authored Shift (#585, ADR-0026 §1) —
     * the derived ownership rule, since no column records who wrote a row (#334 stays open).
     * A Member owns a Shift, and may edit or delete it, exactly while:
     *
     * - the owning Group is **self-serve**;
     * - the Shift's **capacity is 1** (a Member never authors a wider slot, so a wider one
     *   is a Scheduler's and off-limits);
     * - the Shift's **only Sign-up is the Member's** (they hold the one seat, so the Shift is
     *   theirs); and
     * - the Shift **has not started** — edit and delete close at the start, the same bound
     *   take and drop answer to (#554, ADR-0026 §6). After the start only the Scheduler acts.
     *
     * The accepted edge (ADR-0026 §1): a capacity-1 Shift a Scheduler authored and placed
     * this Member on is editable by them too, because ownership is derived, not authored.
     *
     * Read directly, never as a Gate ability, on purpose (#647, ADR-0017 §5): the
     * `Gate::before` super-tier short-circuit would grant it to the President on every
     * Shift. This is an ownership rule, not a permission, so it binds super-tier too;
     * officers edit other people's Shifts through the Scheduler's Edit.
     */
    public function isSelfServeOwnedBy(Member $member): bool
    {
        // Read the seats from the loaded relation when the caller already has them (the Agenda
        // payload loads every Shift's Sign-ups), and query once when it does not (a route-bound
        // Shift in a Form Request) — so this never lazy-loads under strict mode nor N+1s a page.
        $this->loadMissing('signUps');
        $signUps = $this->signUps;

        return $this->schedule->group->self_serve_shifts
            && $this->capacity === 1
            && $signUps->count() === 1
            && $signUps->first()->member_id === $member->getKey()
            && ! $this->hasStarted();
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
