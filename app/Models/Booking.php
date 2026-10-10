<?php

namespace App\Models;

use App\Enums\ShiftAudience;
use App\Policies\BookingPolicy;
use App\Support\Scheduling\GroupTourSchedule;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A Booking (ADR-0032 §1) — an outside client engaging a Group for one dated tour, which Docents
 * and GDR call a group tour. The Booking holds the client half: the {@see Tour} given, the client,
 * expected visitors, {@see BookingType}, group leader, ROM order number and date, comments, and
 * the Statistician's Earned correction (§7). Its one {@see Shift} holds the staffing: the times,
 * the Group's group-tour kind, and the docents needed as capacity. The Shift sits on its month's
 * group-tour Schedule ({@see GroupTourSchedule}); deleting the Shift deletes the Booking.
 *
 * Client, leader, order number and comments are content, stored as-authored (ADR-0004). Who may
 * read which fields is the {@see BookingPolicy} (§5).
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'shift_id',
        'tour_id',
        'booking_type_id',
        'client',
        'visitors',
        'leader',
        'order_number',
        'order_date',
        'comments',
        'earned_correction',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visitors' => 'integer',
            'order_date' => 'date',
            'earned_correction' => 'decimal:2',
        ];
    }

    /**
     * Add a Booking and its one Shift, in one transaction (#795, ADR-0032 §1, §4). The Shift goes
     * on the month's group-tour Schedule ({@see GroupTourSchedule::for()}), created and published
     * on the month's first Booking. It takes the Group's group-tour shift kind, the docents needed
     * as its capacity, and the Group audience: a Booking's Shift is never advertised to other
     * Groups. The caller has checked the Group has a group-tour kind and the times sit in one day.
     *
     * @param  array<string, mixed>  $attributes  the Booking's own fields (tour, type, client, ...)
     */
    public static function book(
        Group $group,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $docentsNeeded,
        array $attributes,
        ?string $locale = null,
    ): self {
        return DB::transaction(function () use ($group, $startsAt, $endsAt, $docentsNeeded, $attributes, $locale): self {
            $schedule = GroupTourSchedule::for($group, $startsAt, $locale);

            $shift = $schedule->shifts()->create([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'capacity' => $docentsNeeded,
                'shift_kind_id' => $group->group_tour_shift_kind_id,
                'audience' => ShiftAudience::Group,
            ]);

            return self::create([
                ...$attributes,
                'group_id' => $group->id,
                'shift_id' => $shift->id,
            ]);
        });
    }

    /**
     * The Group that runs this Booking.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Booking's one Shift — its times, kind and docents needed.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The Tour the client booked (ADR-0033). The Shift offers this Tour alone
     * ({@see Shift::toursOffered()}).
     *
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * The Booking's billing class.
     *
     * @return BelongsTo<BookingType, $this>
     */
    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class);
    }
}
