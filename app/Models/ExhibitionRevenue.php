<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Group's exhibition revenue for one month (#800, ADR-0032 §12): a money figure the Statistician
 * enters from the Tour Summary, which adds it to the grand total. One row per Group and `YYYYMM`
 * month, as on {@see HoursRecord}.
 */
class ExhibitionRevenue extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'year_month',
        'amount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Set a Group's figure for a month. Null removes it, so the month reads zero again.
     */
    public static function enter(Group $group, string $yearMonth, ?string $amount): void
    {
        if ($amount === null) {
            self::query()->where('group_id', $group->id)->where('year_month', $yearMonth)->delete();

            return;
        }

        self::updateOrCreate(
            ['group_id' => $group->id, 'year_month' => $yearMonth],
            ['amount' => $amount],
        );
    }

    /**
     * The Group this figure belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
