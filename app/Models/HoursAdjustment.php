<?php

namespace App\Models;

use Database\Factories\HoursAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Hours adjustment (ADR-0022 §6) — one append-only entry in the provenance log behind
 * the extra-hours screen. Records the signed `delta` applied to an {@see HoursRecord} and
 * the Member who authored it, so a wrong number always has an author and the history
 * survives later corrections. Rows are only ever inserted; there is no update path.
 */
class HoursAdjustment extends Model
{
    /** @use HasFactory<HoursAdjustmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'hours_record_id',
        'delta',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
        ];
    }

    /**
     * The Hours record this adjustment targets.
     *
     * @return BelongsTo<HoursRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HoursRecord::class, 'hours_record_id');
    }

    /**
     * The Member who authored the adjustment — always the authenticated writer.
     *
     * @return BelongsTo<Member, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }
}
