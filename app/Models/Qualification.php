<?php

namespace App\Models;

use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Qualification (ADR-0033 §3) — a Member holding a Tour in a Group. Keyed on the Membership,
 * so removing the Membership removes its qualifications. An inactive qualification is kept but
 * counts for nothing. The Last vet date is shown, never enforced.
 */
class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_member_id',
        'tour_id',
        'active',
        'last_vet_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_vet_date' => 'date',
        ];
    }

    /**
     * The Membership holding this qualification.
     *
     * @return BelongsTo<GroupMember, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    /**
     * The Tour held.
     *
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
