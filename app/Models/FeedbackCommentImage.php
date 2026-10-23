<?php

namespace App\Models;

use Database\Factories\FeedbackCommentImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image on a Feedback comment (#778, ADR-0029 §8, §9): the comment's twin of a
 * {@see FeedbackScreenshot}. It lives on the `feedback` connection beside its comment. The
 * file is on the private `local` disk under a UUID name (`storage_path`); the original
 * filename is kept only for the download (docs/conventions.md § Documents).
 */
class FeedbackCommentImage extends Model
{
    /** @use HasFactory<FeedbackCommentImageFactory> */
    use HasFactory;

    protected $connection = 'feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'original_filename',
        'storage_path',
        'mime_type',
        'size_bytes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FeedbackComment, $this>
     */
    public function feedbackComment(): BelongsTo
    {
        return $this->belongsTo(FeedbackComment::class);
    }
}
