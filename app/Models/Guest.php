<?php

namespace App\Models;

use App\Enums\Reply;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Member on an Invitation's guest list (ADR-0034): one row per (Invitation, Member).
 * The row holds the Reply, so removing a Guest removes their Reply with it.
 */
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    /**
     * A new Guest has No reply.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reply' => Reply::NoReply->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reply' => Reply::class,
        ];
    }

    /**
     * The Invitation this Guest is on.
     *
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * The invited Member.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
