<?php

namespace App\Models;

use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Group's Tour (#788, ADR-0033 §1) — one concrete tour a Docent or Guide gives ("Museum
 * Highlights", "Le choix du guide"). A shift kind is only the slot label; it maps to one or more
 * Tours through the `shift_kind_tour` pivot. A Member holds a Tour through a
 * {@see Qualification}; a Tour flagged `open_to_all` needs none.
 *
 * The same maintenance shape as {@see ShiftKind}: group-scoped, an `active` flag to retire
 * without deleting, an authored `sort_order`. `name` is officer-authored content, stored
 * single-column and as-authored — never translated (ADR-0004).
 */
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'name',
        'active',
        'open_to_all',
        'starter',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'open_to_all' => 'boolean',
            'starter' => 'boolean',
        ];
    }

    /**
     * The Group this Tour belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The shift kinds this Tour is given on.
     *
     * @return BelongsToMany<ShiftKind, $this>
     */
    public function shiftKinds(): BelongsToMany
    {
        return $this->belongsToMany(ShiftKind::class)->withTimestamps();
    }

    /**
     * The qualifications held on this Tour.
     *
     * @return HasMany<Qualification, $this>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }

    /**
     * The Sign-ups that record this Tour.
     *
     * @return HasMany<SignUp, $this>
     */
    public function signUps(): HasMany
    {
        return $this->hasMany(SignUp::class);
    }

    /**
     * The Bookings that give this Tour (#795, ADR-0032 §1). A Tour a Booking names is retired, not
     * deleted.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Limit the query to active Tours — the ones still offered.
     *
     * @param  Builder<Tour>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Order Tours the one way the app lists them: the Group's authored order, then name.
     *
     * @param  Builder<Tour>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
