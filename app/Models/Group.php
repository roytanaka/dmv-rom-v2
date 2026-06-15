<?php

namespace App\Models;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\Scope;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The spine's organizing entity (ADR-0010): every committee, program, working
 * group, project, and cohort is a Group. Kind / Scope / Lifecycle are stored
 * explicitly; the six capability flags switch features on per Group.
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'description',
        'kind',
        'scope',
        'lifecycle_state',
        'time_boxed',
        'start_date',
        'end_date',
        'display_order',
        'has_meetings',
        'has_documents',
        'has_scheduling',
        'has_content_catalog',
        'has_vetting',
        'has_hours_stats',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => Kind::class,
            'scope' => Scope::class,
            'lifecycle_state' => LifecycleState::class,
            'time_boxed' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'has_meetings' => 'boolean',
            'has_documents' => 'boolean',
            'has_scheduling' => 'boolean',
            'has_content_catalog' => 'boolean',
            'has_vetting' => 'boolean',
            'has_hours_stats' => 'boolean',
        ];
    }

    /**
     * The Group directly above this one in the tree (null for the root).
     *
     * @return BelongsTo<Group, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    /**
     * The Groups directly below this one in the tree.
     *
     * @return HasMany<Group, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    /**
     * Every membership in this Group — the roster source.
     *
     * @return HasMany<GroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Limit the query to Groups that are still alive: lifecycle Active, and not
     * an expired time-boxed Group (one whose end_date has passed). A time-boxed
     * Group with no end_date is treated as still open.
     *
     * @param  Builder<Group>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('lifecycle_state', LifecycleState::Active)
            ->where(function (Builder $query) {
                $query->where('time_boxed', false)
                    ->orWhereNull('end_date')
                    ->orWhereDate('end_date', '>=', now());
            });
    }
}
