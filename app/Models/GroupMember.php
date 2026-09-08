<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\GroupMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A membership — the pivot row binding a Member to a Group with a within-Group
 * `status` (App\Enums\MembershipStatus) and an optional LOA window. The single
 * authoritative source of "who is in this Group, with what standing" that every
 * authorization decision and roster reads (PRD #126, slice 3).
 */
class GroupMember extends Model
{
    /** @use HasFactory<GroupMemberFactory> */
    use HasFactory;

    /**
     * The pivot keeps its own surrogate id and timestamps, so the conventional
     * table name is set explicitly (Eloquent would otherwise pluralise it).
     *
     * @var string
     */
    protected $table = 'group_member';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'member_id',
        'status',
        'loa_start',
        'loa_end',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'loa_start' => 'date',
            'loa_end' => 'date',
        ];
    }

    /**
     * The Group this membership is in.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Member this membership belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The roles this membership carries — a membership may hold several at once
     * (e.g. a Chair who also schedules).
     *
     * @return HasMany<GroupMemberRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(GroupMemberRole::class);
    }
}
