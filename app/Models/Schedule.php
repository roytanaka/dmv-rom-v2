<?php

namespace App\Models;

use App\Enums\ScheduleState;
use App\Policies\SchedulePolicy;
use Carbon\CarbonImmutable;
use Database\Factories\ScheduleFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Group's Schedule (#353, PRD #352, ADR-0021 §1) — the scheduling container that
 * ADR-0015 was missing. Belongs to exactly one Group (which has-many schedules) and
 * exists only when the Group's `has_scheduling` capability is on. A Schedule is a
 * name, a date range, a `state`, and an optional description; it holds Shifts
 * (#355, ADR-0021 §2).
 *
 * A `draft` is visible only to the Group's schedule admins; a `published` one follows
 * the Group's listing visibility ({@see SchedulePolicy}). `name` / `description` are
 * officer-authored content, stored single-column and as-authored (ADR-0004). Past-ness
 * derives from `ends_on` — there is no `archived` flag and nothing is hidden by date.
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'name',
        'starts_on',
        'ends_on',
        'state',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'state' => ScheduleState::class,
        ];
    }

    /**
     * The Group that owns this Schedule.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Shifts on this Schedule — the slots it holds (#355, ADR-0021 §2). A Shift has
     * no state; it inherits its whole context from this Schedule.
     *
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Limit the query to published Schedules — the only ones an audience beyond the
     * Group's schedule admins may see.
     *
     * @param  Builder<Schedule>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('state', ScheduleState::Published);
    }

    /**
     * Whether this Schedule is still current — its range has not yet passed. Past-ness
     * derives from `ends_on` (ADR-0021 §1); a Schedule ending today is still current.
     * Resolved in PHP against the given day so date semantics never depend on the DB
     * engine.
     */
    public function isCurrent(DateTimeInterface $asOf): bool
    {
        return $this->ends_on->greaterThanOrEqualTo(
            CarbonImmutable::instance($asOf)->startOfDay(),
        );
    }

    /**
     * Whether an instant-pair — a Shift's `starts_at` / `ends_at` — falls inside this
     * Schedule's date range (ADR-0021 §2). Day-resolution and both ends inclusive: the
     * range runs from the first day's start to the last day's end. This is the single
     * comparison behind both directions of range enforcement — a Shift may not sit
     * outside its Schedule, and a Schedule may not shrink away from its Shifts. Resolved
     * in PHP so the comparison never depends on the DB engine.
     */
    public function coversInterval(DateTimeInterface $startsAt, DateTimeInterface $endsAt): bool
    {
        return $this->starts_on->startOfDay()->lessThanOrEqualTo($startsAt)
            && $this->ends_on->endOfDay()->greaterThanOrEqualTo($endsAt);
    }
}
