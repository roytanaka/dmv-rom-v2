<?php

namespace App\Models;

use Database\Factories\BookingTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Group's booking type (#794, ADR-0032 §6) — the billing class of a {@see Booking}: Tour Paid,
 * Tour Free, Tour Internal, Spot Paid, Spot Free. Each carries a rate per visitor and a rate per
 * docent-hour, from which Earned is worked out (§7).
 *
 * The same maintenance shape as {@see Tour}: group-scoped, an `active` flag to retire without
 * deleting, an authored `sort_order`. `name` is officer-authored content, stored single-column
 * and as-authored — never translated (ADR-0004).
 */
class BookingType extends Model
{
    /** @use HasFactory<BookingTypeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'name',
        'rate_per_visitor',
        'rate_per_docent_hour',
        'active',
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
            'rate_per_visitor' => 'decimal:2',
            'rate_per_docent_hour' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * The Group this type belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Bookings of this type.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Order types the one way the app lists them: the Group's authored order, then name.
     *
     * @param  Builder<BookingType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
