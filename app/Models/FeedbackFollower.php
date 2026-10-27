<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * A Follower (#857, spec #856, ADR-0029 §15): an email address that gets an email for each
 * new comment on one Feedback item. It lives on the `feedback` connection beside its item.
 * It is an address, not a Member (§2), and it appears on no page.
 *
 * The address is trimmed and lowercased on every save ({@see normalize()}), so the same
 * address typed with capitals or spaces is one Follower. The locale is the one the Tester
 * used when they followed; every email to this Follower renders in it.
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
     * An address as stored and compared: trimmed and lowercased.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => self::normalize($value));
    }

    /**
     * @return BelongsTo<FeedbackItem, $this>
     */
    public function feedbackItem(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class);
    }

    /**
     * The stop link in every email to this Follower: a signed URL with no expiry, in the
     * Follower's locale (ADR-0008), that needs no login.
     *
     * Laravel's `URL::signedRoute()` can only build the URL for the locale whose routes this
     * request registered, so the localized URL is built from the route files and signed the
     * way Laravel signs one: an HMAC of the absolute URL under the app key. The `signed`
     * middleware checks it.
     */
    public function stopUrl(): string
    {
        $url = LaravelLocalization::getURLFromRouteNameTranslated(
            $this->locale,
            'routes.feedback.unfollow',
            ['follower' => $this->id],
        );

        return $url.'?signature='.hash_hmac('sha256', $url, config('app.key'));
    }
}
