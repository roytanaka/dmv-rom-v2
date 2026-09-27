<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Database\Factories\FeedbackItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'status' => 'new',
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
}
