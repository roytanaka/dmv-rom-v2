<?php

namespace App\Models;

use App\Enums\BroadcastKind;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Policies\BroadcastPolicy;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Broadcast sent record (spec #479, ADR-0024 §6) — one row per composer send, the
 * message an officer wrote and the Audience it went to. The send writes this row, one
 * Broadcast {@see Delivery} per recipient, and one sender-copy Delivery; nothing sends
 * in the request. The Delivery rows are the recipient list, kept forever, so the sent
 * and failed counts are derived from them ({@see sentCount()}, {@see failedCount()})
 * rather than stored.
 *
 * A {@see BroadcastPolicy} grants view to Records and the sender; no
 * screen reads it this pass.
 */
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'sender_id',
        'group_id',
        'audience_key',
        'audience_label',
        'edited',
        'recipient_count',
        'subject',
        'body',
        'attachments',
        'queued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BroadcastKind::class,
            'edited' => 'boolean',
            'attachments' => 'array',
            'queued_at' => 'datetime',
        ];
    }

    /**
     * The officer (or Member) who wrote it — the mail's Reply-To and one of the two the
     * view policy grants.
     *
     * @return BelongsTo<Member, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sender_id');
    }

    /**
     * The Group the send was scoped to; null for an org-wide Broadcast or a Direct
     * message. The From display name is this Group's name, or the app's name when null.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Every Delivery written for this Broadcast — the per-recipient rows and the sender
     * copy alike, since both carry the `broadcast_id`.
     *
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * The recipient Delivery rows (the Broadcast kind), without the sender copy — the
     * list the sent and failed counts are derived from.
     *
     * @return HasMany<Delivery, $this>
     */
    public function recipientDeliveries(): HasMany
    {
        return $this->deliveries()->where('kind', DeliveryKind::Broadcast);
    }

    /**
     * How many recipient Deliveries have been sent — derived, not stored (ADR-0024 §6).
     */
    public function sentCount(): int
    {
        return $this->recipientDeliveries()->where('state', DeliveryState::Sent)->count();
    }

    /**
     * How many recipient Deliveries have failed — derived, not stored (ADR-0024 §6).
     */
    public function failedCount(): int
    {
        return $this->recipientDeliveries()->where('state', DeliveryState::Failed)->count();
    }

    /**
     * The display name the From line wears: the Group's name for a Group Broadcast, the
     * app's name for an org-wide send (ADR-0024 §3).
     */
    public function fromName(): string
    {
        return $this->group?->name ?? config('app.name');
    }
}
