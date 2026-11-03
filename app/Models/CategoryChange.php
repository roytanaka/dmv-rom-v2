<?php

namespace App\Models;

use App\Actions\ChangeCategory;
use App\Enums\Category;
use App\Enums\DepartureReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a Member's Category history (#920, ADR-0038 §4). Written and removed only through
 * {@see ChangeCategory}. `category` is a {@see Category} value or {@see REINSTATE}, the marker
 * Reinstate writes ahead of Active; the marker is history only and never becomes the Category.
 */
class CategoryChange extends Model
{
    /** The marker entry Reinstate writes before Active, as the Legacy app did. */
    public const REINSTATE = 'reinstate';

    protected $fillable = [
        'category',
        'date',
        'departure_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'departure_reason' => DepartureReason::class,
        ];
    }

    /**
     * Newest first, as the record lists them and the recompute reads them: by date, then by entry
     * order so Reinstate's same-day Active outranks its marker.
     *
     * @param  Builder<CategoryChange>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('id');
    }

    /** The Category this entry sets, or null for the Reinstate marker. */
    public function toCategory(): ?Category
    {
        return Category::tryFrom($this->category);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
