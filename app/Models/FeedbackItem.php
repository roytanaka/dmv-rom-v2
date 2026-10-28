<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Support\LocalizedUrl;
use Database\Factories\FeedbackItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * A Feedback item (ADR-0029): one thing a Tester sends from a non-production app. It lives
 * on the `feedback` connection, a second database that deploys never reset, so it survives
 * staging's `migrate:fresh --seed`.
 *
 * It holds no ids into the main database (§2). The Tester, the logged-in Member, and the
 * impersonator are plain-text names, because a reseed gives every Member a new id.
 */
class FeedbackItem extends Model
{
    /** @use HasFactory<FeedbackItemFactory> */
    use HasFactory;

    protected $connection = 'feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tester_name',
        'type',
        'status',
        'message',
        'page_url',
        'user_agent',
        'viewport_width',
        'viewport_height',
        'route_name',
        'locale',
        'member_name',
        'member_email',
        'impersonator_name',
        'app_version',
    ];

    /**
     * New items start as New (§7), whether or not the caller names a status.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => FeedbackStatus::New->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'status' => FeedbackStatus::class,
            'viewport_width' => 'integer',
            'viewport_height' => 'integer',
        ];
    }

    /**
     * The page URL as a link, or null. The client sent it, so it is linked only when it
     * is a path inside the app: one leading slash, then no second slash or backslash
     * (which a browser reads as another host), and no whitespace or control characters
     * (which a browser strips, so they could hide either). Anything else shows as text.
     */
    public function pageHref(): ?string
    {
        if ($this->page_url === null || preg_match('#^/(?![/\\\\])[^\\s\\x00-\\x1f\\x7f]*$#', $this->page_url) !== 1) {
            return null;
        }

        return $this->page_url;
    }

    /**
     * The flat comment list under this item (§8).
     *
     * @return HasMany<FeedbackComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class);
    }

    /**
     * The screenshots sent with this item, at most 3 (§9).
     *
     * @return HasMany<FeedbackScreenshot, $this>
     */
    public function screenshots(): HasMany
    {
        return $this->hasMany(FeedbackScreenshot::class);
    }

    /**
     * The addresses that get an email for each new comment (#857, §15). Never shown on a page.
     *
     * @return HasMany<FeedbackFollower, $this>
     */
    public function followers(): HasMany
    {
        return $this->hasMany(FeedbackFollower::class);
    }

    /**
     * Follow this item with an address: insert or keep (§15). The address comes normalized
     * from the request, so a second follow with the same address keeps the first Follower and
     * its locale. `wasRecentlyCreated` on the result says whether this follow made a new
     * Follower, the only case that earns a receipt.
     */
    public function follow(string $email, string $locale): FeedbackFollower
    {
        return $this->followers()->firstOrCreate(['email' => $email], ['locale' => $locale]);
    }

    /**
     * How an email names this item, in the current locale: items have no title, so the
     * number, the type, and the start of the message (§15). The subject and the body use it.
     */
    public function label(): string
    {
        return __('feedback.follow.item_label', [
            'id' => $this->id,
            'type' => __($this->type->labelKey()),
            'excerpt' => Str::limit(Str::squish($this->message), 50),
        ]);
    }

    /**
     * The absolute link to this item's page in a locale (ADR-0008), for emails.
     */
    public function urlFor(string $locale): string
    {
        return LocalizedUrl::to($locale, 'feedback.show', ['feedbackItem' => $this->id]);
    }

    /**
     * Every image on this item's comments (#778), through the comments. Countable with
     * `withCount('commentImages')`.
     *
     * @return HasManyThrough<FeedbackCommentImage, FeedbackComment, $this>
     */
    public function commentImages(): HasManyThrough
    {
        return $this->hasManyThrough(FeedbackCommentImage::class, FeedbackComment::class);
    }
}
