<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A board position a Member has held (#918, ADR-0038 §3): free text with free-text years, as the
 * Legacy app keeps them. Hand-kept history, separate from the Roles a Membership holds today.
 */
class BoardPosition extends Model
{
    protected $fillable = [
        'position',
        'years',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
