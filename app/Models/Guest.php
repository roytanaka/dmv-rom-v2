<?php

namespace App\Models;

use App\Enums\Reply;
use App\Support\Notices\InvitationFullNoticeWriter;
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
     * Record a Reply and its chosen Options, the Host's write. It asks nothing about who is
     * replying or when: the caller has already decided that, and it skips the Guest limit
     * (ADR-0034 §9).
     *
     * @param  list<int>  $optionIds  Options of this Guest's Invitation
     */
    public function setReply(Reply $reply, array $optionIds = []): void
    {
        DB::transaction(function () use ($reply, $optionIds): void {
            $this->write($this->lockInvitation(), $reply, $optionIds);
        });
    }

    /**
     * Record a Guest's own Reply, held to the Guest limit (ADR-0034 §9). Returns false, and
     * changes nothing, when this would take a spot on a full Invitation. A Guest who already
     * holds a spot is not taking one, so they can still change their Options.
     *
     * @param  list<int>  $optionIds  Options of this Guest's Invitation
     */
    public function setReplyWithinLimit(Reply $reply, array $optionIds = []): bool
    {
        return DB::transaction(function () use ($reply, $optionIds): bool {
            $invitation = $this->lockInvitation();

            if ($this->takesSpot($reply) && $invitation->isFull()) {
                return false;
            }

            $this->write($invitation, $reply, $optionIds);

            return true;
        });
    }

    /**
     * Take the Invitation's row lock, which serializes every Reply on it, and re-read this
     * row under it. So two Guests cannot take the last spot, and a Host-set Reply and a Guest's
     * accept cannot both see the count reach the limit. Call it before any other read in the
     * transaction, so the count sees every Reply committed before the lock was granted.
     */
    private function lockInvitation(): Invitation
    {
        $invitation = Invitation::query()->lockForUpdate()->findOrFail($this->invitation_id);

        $this->refresh();

        return $invitation;
    }

    /**
     * The one write every Reply goes through, under the Invitation's lock. An Option survives
     * only on an Accepted Reply, so a Decline clears the choices. An accept that brings the
     * count up to the Guest limit queues the Host's Notice, in the same transaction. A count
     * that goes past the limit, or falls back to it, queues nothing.
     *
     * @param  list<int>  $optionIds
     */
    private function write(Invitation $invitation, Reply $reply, array $optionIds): void
    {
        $takesSpot = $this->takesSpot($reply);

        $this->reply = $reply;
        $this->save();

        $this->options()->sync($reply === Reply::Accepted ? $optionIds : []);

        if ($takesSpot && $invitation->acceptedCount() === $invitation->guest_limit) {
            app(InvitationFullNoticeWriter::class)->write($invitation);
        }
    }

    /**
     * Whether this Reply would add to the Accepted count.
     */
    private function takesSpot(Reply $reply): bool
    {
        return $reply === Reply::Accepted && $this->reply !== Reply::Accepted;
    }
}
