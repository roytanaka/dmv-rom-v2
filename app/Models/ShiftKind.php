<?php

namespace App\Models;

use Database\Factories\ShiftKindFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Group's ShiftKind (#355, PRD #352, ADR-0021 §3) — one entry in the small per-Group
 * vocabulary of kinds of shift ("Level 1 Oslo gate", "Highlights tour"), ADR-0015's
 * "Catalog" renamed and scoped. A real table rather than a free string so a
 * qualification requirement has somewhere to hang later; the first pass builds none of
 * that machinery — the slot is decided and stays empty.
 *
 * Scoped per Group (which has-many shiftKinds). A Shift's `shift_kind_id` is nullable —
 * Reception's Shifts carry null. Rows are seeded; the maintenance CRUD screen is
 * deferred (ADR-0021 §3). `name` is officer-authored content, stored single-column and
 * as-authored — never translated (ADR-0004).
 */
class ShiftKind extends Model
{
    /** @use HasFactory<ShiftKindFactory> */
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
        ];
    }

    /**
     * The Group this kind belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Shifts carrying this kind.
     *
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Limit the query to active kinds — the ones still offered to new Shifts. An
     * inactive kind still labels the Shifts that already carry it.
     *
     * @param  Builder<ShiftKind>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
