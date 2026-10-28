<?php

namespace App\Models;

use App\Http\Requests\Concerns\FollowsFeedbackItem;
use App\Support\LocalizedUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Follower (#857, spec #856, ADR-0029 §15): an email address that gets an email for each
 * new comment on one Feedback item. It lives on the `feedback` connection beside its item.
 * It is an address, not a Member (§2), and it appears on no page.
 *
 * The address is stored as the request normalized it, trimmed and lowercased
 * ({@see FollowsFeedbackItem::authorEmail()}), so the same address typed with capitals or
 * spaces is one Follower. The locale is the one the Tester used when they followed; every
 * email to this Follower renders in it.
 */
class FeedbackFollower extends Model
{
    protected $connection = 'feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'locale',
    ];

    /**
     * @return BelongsTo<FeedbackItem, $this>
     */
    public function feedbackItem(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class);
    }

    /**
     * The stop link in every email to this Follower: a signed URL with no expiry, in the
     * Follower's locale (ADR-0008), that needs no login. See {@see LocalizedUrl::signed()}.
     */
    public function stopUrl(): string
    {
        return LocalizedUrl::signed($this->locale, 'feedback.unfollow', ['follower' => $this->id]);
    }
}
