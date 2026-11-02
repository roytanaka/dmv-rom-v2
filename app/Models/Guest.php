<?php

namespace App\Models;

use App\Enums\Reply;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * The Options this Guest chose. Only an Accepted Reply has any.
     *
     * @return BelongsToMany<Option, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class);
    }

    /**
     * Record a Reply and its chosen Options, the one write every Reply goes through. It
     * asks nothing about who is replying or when: the caller has already decided that. An
     * Option survives only on an Accepted Reply, so a Decline clears the choices.
     *
     * @param  list<int>  $optionIds  Options of this Guest's Invitation
     */
    public function setReply(Reply $reply, array $optionIds = []): void
    {
        DB::transaction(function () use ($reply, $optionIds): void {
            $this->reply = $reply;
            $this->save();

            $this->options()->sync($reply === Reply::Accepted ? $optionIds : []);
        });
    }
}
