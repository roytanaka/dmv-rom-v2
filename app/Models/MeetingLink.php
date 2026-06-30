<?php

namespace App\Models;

use App\Enums\MeetingLinkKind;
use Database\Factories\MeetingLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A labelled external link on a meeting (#190, PRD #186) — one of the conventional
 * agenda / minutes / report set. The `kind` selects the translated label (chrome,
 * ADR-0004); the `url` is content. Upgrades to an access-controlled document when
 * the Documents capability lands.
 */
class MeetingLink extends Model
{
    /** @use HasFactory<MeetingLinkFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'kind',
        'url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MeetingLinkKind::class,
        ];
    }

    /**
     * The meeting this link is attached to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
