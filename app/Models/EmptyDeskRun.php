<?php

namespace App\Models;

use Database\Factories\EmptyDeskRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One empty-desk run (#487, spec #479, ADR-0024 §7) — the record that a Group's alert fired on
 * a given cadence day, with the count of unstaffed watched Shifts it found. Written on every run
 * day, silent or not; the unique (Group, run date) grain makes it the idempotency key the daily
 * pass reads to decide whether today's alert has already run.
 */
class EmptyDeskRun extends Model
{
    /** @use HasFactory<EmptyDeskRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'run_date',
        'open_shift_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'open_shift_count' => 'integer',
        ];
    }

    /**
     * The Group this run belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
