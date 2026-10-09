<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Database\Factories\FeedbackItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
