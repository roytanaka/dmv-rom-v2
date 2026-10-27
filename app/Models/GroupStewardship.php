<?php

namespace App\Models;

use App\Enums\StewardshipFunction;
use Database\Factories\GroupStewardshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Group's declaration that it stewards an org-wide system function
 * (`App\Enums\StewardshipFunction`) the rest of the DMV depends on — e.g. the
 * Records Group stewarding `member_admin` (PRD #126, slice 5). Stored as its own
 * row so a Group may steward several functions, and the steward of a function can
 * move between Groups as a data change.
 */
class GroupStewardship extends Model
{
    /** @use HasFactory<GroupStewardshipFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'function',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'function' => StewardshipFunction::class,
        ];
    }

    /**
     * The Group doing the stewarding.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Member who first assigned this stewardship to the Group (ADR-0036 §8); null
     * for a seeded row.
     *
     * @return BelongsTo<Member, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /**
     * The Member who last saved this stewardship (ADR-0036 §8); null for a seeded row.
     * The Stewardships page's "last changed" line reads it.
     *
     * @return BelongsTo<Member, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'updated_by');
    }
}
