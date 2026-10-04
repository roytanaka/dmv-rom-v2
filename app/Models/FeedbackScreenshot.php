<?php

namespace App\Models;

use Database\Factories\FeedbackScreenshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A screenshot on a Feedback item (#678, ADR-0029 §9). It lives on the `feedback`
 * connection beside its item. The file is on the private `local` disk under a UUID name
 * (`storage_path`); the original filename is kept only for the download
 * (docs/conventions.md § Documents).
 */
class FeedbackScreenshot extends Model
{
    /** @use HasFactory<FeedbackScreenshotFactory> */
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
     * @return BelongsTo<FeedbackItem, $this>
     */
    public function feedbackItem(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class);
    }
}
