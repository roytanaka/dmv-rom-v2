<?php

namespace App\Models;

use App\Policies\MeetingPolicy;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Group's meeting (#190, PRD #186) — the Group's first own-data capability.
 * Belongs to exactly one Group (which has-many meetings) and is listed only when
 * the Group's `has_meetings` capability is on. The list is members-only
 * ({@see MeetingPolicy}); a hidden meeting (`is_published` false)
 * is invisible to ordinary members until published.
 *
 * `title` / `description` / `location` are member-authored content, stored
 * single-column and as-authored (ADR-0004). `video_url` is a plain external link.
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'title',
        'description',
        'held_at',
        'location',
        'video_url',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The Group that runs this meeting.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The labelled external links (agenda / minutes / report) attached to this
     * meeting.
     *
     * @return HasMany<MeetingLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(MeetingLink::class);
    }

    /**
     * Limit the query to published meetings — the only ones an ordinary member may
     * see. Hidden meetings are drafts (officer drafting affordances land in #193).
     *
     * @param  Builder<Meeting>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
