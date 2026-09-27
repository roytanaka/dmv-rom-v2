<?php

namespace App\Models;

use Database\Factories\FeedbackCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment on a Feedback item (#677, ADR-0029 §8). It lives on the `feedback`
 * connection beside its item. The Tester is a typed, plain-text name (§5).
 */
class FeedbackComment extends Model
{
    /** @use HasFactory<FeedbackCommentFactory> */
    use HasFactory;

    protected $connection = 'feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tester_name',
        'body',
    ];

    /**
     * @return BelongsTo<FeedbackItem, $this>
     */
    public function feedbackItem(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class);
    }
}
