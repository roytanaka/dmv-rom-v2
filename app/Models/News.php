<?php

namespace App\Models;

use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single item in the org-wide news feed (#155, ADR-0017 §5). The read is
 * org-wide — every logged-in member sees one feed — but each item records the
 * `posting_group_id` of the Group that published it, so attribution is preserved
 * (ADR-0010 announcements delta).
 *
 * Writes are gated by a capability-scoped news-editor role on the posting Group
 * (NewsPolicy); reading is open to every member. `title` / `body` are
 * volunteer-authored content, stored single-column and as-authored (ADR-0004).
 */
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    /**
     * Plural of "news" is "news", so the conventional table name is set
     * explicitly rather than left to the pluraliser.
     *
     * @var string
     */
    protected $table = 'news';

    /**
     * The attributes that are mass assignable. Authority is never client-asserted:
     * the posting Group is set from the gated request, never edited afterwards.
     *
     * @var list<string>
     */
    protected $fillable = [
        'posting_group_id',
        'title',
        'body',
    ];

    /**
     * The Group that published this item — the attribution every news-editor
     * authorization decision reads.
     *
     * @return BelongsTo<Group, $this>
     */
    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'posting_group_id');
    }
}
