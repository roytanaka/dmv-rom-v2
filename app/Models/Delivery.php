<?php

namespace App\Models;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued mail (spec #479, ADR-0024 §Delivery). A send writes one Delivery per recipient
 * and the every-minute Drain empties the queue; a row is the sent record too, kept forever.
 *
 * The row snapshots the recipient's address (`email`) at write time and, for the kinds whose
 * source may be gone by drain time, a `payload` snapshot of what the mail needs. `state`
 * walks the {@see DeliveryState} lifecycle; `kind` ({@see DeliveryKind}) decides which
 * Mailable the Drain builds. Failure, retry, and expiry columns are wired by a later ticket
 * (#482); this ticket writes and sends `Notice` rows only.
 */
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'member_id',
        'email',
        'broadcast_id',
        'shift_id',
        'payload',
        'state',
        'attempts',
        'next_attempt_at',
        'expires_at',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DeliveryKind::class,
            'state' => DeliveryState::class,
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * The Member this row is addressed to. The address itself is the snapshotted `email`
     * column, not this relation — the relation exists to read the recipient's saved locale
     * at send time, so each copy renders in the recipient's own language.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The Shift a Reminder is keyed to; null for every other kind.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
