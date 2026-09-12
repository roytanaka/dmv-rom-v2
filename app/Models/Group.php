<?php

namespace App\Models;

use App\Enums\GroupBanner;
use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Enums\Scope;
use App\Enums\StewardshipFunction;
use App\Support\Audiences\AudienceResolver;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * The slug of the org root — the single parentless "DMV" Group at the top of the
     * tree (ADR-0010, ADR-0020 §B). Named so the My Groups builder can resolve the org
     * node without hard-coding the string, and single-sourced with {@see OrgTreeSeeder}.
     */
    public const ROOT_SLUG = 'dmv';

    /**
     * The slug of the DMV Executive Group — the top governance body, a single
     * standing committee under the root (CONTEXT.md: "DMV Executive"; not the root
     * itself, and not the super-tier grant). Named so the Board-of-Directors Audience
     * ({@see AudienceResolver}) can resolve the Executive's
     * roster without hard-coding the string, and single-sourced across both seeders,
     * exactly as {@see ROOT_SLUG} single-sources the root.
     */
    public const EXECUTIVE_SLUG = 'executive';

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
        'banner_key',
        'logo_key',
        'kind',
        'scope',
        'listing_visibility',
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
        'has_announcements',
        'collects_visitor_count',
        'collects_extra_interactions',
        'collects_visitor_provenance',
        'visitor_figures_await_booking',
        'hours_multiplier',
        'reminders_enabled',
        'reminder_lead_days',
        'empty_desk_alert_enabled',
        'empty_desk_days_ahead',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'banner_key' => GroupBanner::class,
            'logo_key' => GroupLogo::class,
            'kind' => Kind::class,
            'scope' => Scope::class,
            'listing_visibility' => ListingVisibility::class,
            'lifecycle_state' => LifecycleState::class,
            'time_boxed' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'has_meetings' => 'boolean',
            'has_documents' => 'boolean',
            'has_scheduling' => 'boolean',
            'has_content_catalog' => 'boolean',
            'has_vetting' => 'boolean',
            'has_announcements' => 'boolean',
            'collects_visitor_count' => 'boolean',
            'collects_extra_interactions' => 'boolean',
            'collects_visitor_provenance' => 'boolean',
            'visitor_figures_await_booking' => 'boolean',
            'hours_multiplier' => 'integer',
            'reminders_enabled' => 'boolean',
            'reminder_lead_days' => 'integer',
            'empty_desk_alert_enabled' => 'boolean',
            'empty_desk_days_ahead' => 'integer',
        ];
    }

    /**
     * Route-model bind a Group by its slug, not its id — the slug is the Group's
     * URL identity (ADR-0008): `/groups/{slug}` resolves here and `route('groups.show',
     * $group)` generates the slug URL. An unknown slug 404s automatically.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
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
     * Every Group below this one in the tree, to any depth — the subtree rollup for hours
     * reporting (ADR-0022 §5). Not a relation: the fiscal-year matrix crosses the parentage
     * boundary that ADR-0019 draws for content precisely because hours are a statistic, not
     * content, so a parent's total matches the sum of its children.
     *
     * A recursive load, one query per node, deliberately: the tree is tens of Groups (§ model
     * layer), so a materialized path is not warranted. Queries rather than walking the
     * `children` relation so strict mode's lazy-load guard never bites.
     *
     * @return Collection<int, Group>
     */
    public function descendants(): Collection
    {
        return static::query()
            ->where('parent_id', $this->getKey())
            ->get()
            ->flatMap(fn (Group $child): Collection => $child->descendants()->prepend($child));
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
     * The meetings this Group runs — present only when its `has_meetings`
     * capability is on (#190).
     *
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * The Schedules this Group runs — present only when its `has_scheduling`
     * capability is on (#353, ADR-0021 §1).
     *
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * The ShiftKinds this Group uses to label its Shifts — its small, seeded, per-Group
     * vocabulary (#355, ADR-0021 §3). Present only when the Group runs scheduling.
     *
     * @return HasMany<ShiftKind, $this>
     */
    public function shiftKinds(): HasMany
    {
        return $this->hasMany(ShiftKind::class);
    }

    /**
     * The Members who run this Group's scheduling — the recipients of the Sign-up
     * cancellation email (#358, ADR-0021 §Sign-up "Notification"). These are the Group's
     * `Scheduler`-role holders, with Chair-implication folded in (a Chair acts as Scheduler
     * within its own Group — ADR-0011), matching the schedule-admin gate the SchedulePolicy
     * enforces everywhere else. Resolved via eager-loaded memberships (three queries: roster,
     * members, roles); empty on a Group that has no Scheduler (and, by construction, on one
     * that runs no scheduling).
     *
     * @return Collection<int, Member>
     */
    public function schedulers(): Collection
    {
        return $this->memberships()
            ->with(['member', 'roles'])
            ->get()
            ->filter(fn (GroupMember $membership) => $membership->roles->contains('role', Role::Scheduler)
                || $membership->roles->contains('role', Role::Chair))
            ->map(fn (GroupMember $membership) => $membership->member)
            ->values();
    }

    /**
     * The empty-desk alert runs recorded for this Group (#487, ADR-0024 §7) — one per cadence
     * day the alert fired. Read by the daily pass as the "already ran today" idempotency key.
     *
     * @return HasMany<EmptyDeskRun, $this>
     */
    public function emptyDeskRuns(): HasMany
    {
        return $this->hasMany(EmptyDeskRun::class);
    }

    /**
     * The org-wide system functions this Group stewards — a Group may steward
     * several (e.g. the Records Group stewarding `member_admin`).
     *
     * @return HasMany<GroupStewardship, $this>
     */
    public function stewardships(): HasMany
    {
        return $this->hasMany(GroupStewardship::class);
    }

    /**
     * Limit the query to Groups that steward the given org-wide function — the
     * input the authorization layer reads to resolve "who runs this system function".
     *
     * @param  Builder<Group>  $query
     */
    public function scopeStewarding(Builder $query, StewardshipFunction $function): void
    {
        $query->whereHas('stewardships', function (Builder $query) use ($function) {
            $query->where('function', $function);
        });
    }

    /**
     * The Group that stewards the given org-wide function, or null if none does.
     * `Group::stewardOf(StewardshipFunction::MemberAdmin)` resolves the Records
     * Group; member-administration authority is membership in it (ADR-0011).
     */
    public static function stewardOf(StewardshipFunction $function): ?self
    {
        return static::stewarding($function)->first();
    }

    /**
     * The DMV Executive Group, or null if none is seeded — the roster the
     * Board-of-Directors Audience resolves to (ADR-0024 §5). Resolved by the
     * well-known {@see EXECUTIVE_SLUG}, the same way {@see stewardOf()} resolves the
     * Records Group; the Executive is a specific Group, not an org-wide function, so
     * it is keyed on its slug rather than a stewardship row.
     */
    public static function executive(): ?self
    {
        return static::where('slug', self::EXECUTIVE_SLUG)->first();
    }

    /**
     * Whether this Group is still alive — the instance twin of {@see scopeActive()},
     * for a Group already in memory (e.g. walking a Member's loaded memberships).
     * Lifecycle Active, and not an expired time-boxed Group; a time-boxed Group with
     * no end_date is treated as still open. Mirrors the scope's predicate exactly so
     * the two never drift.
     */
    public function isActive(): bool
    {
        if ($this->lifecycle_state !== LifecycleState::Active) {
            return false;
        }

        if (! $this->time_boxed || $this->end_date === null) {
            return true;
        }

        return $this->end_date->startOfDay()->gte(now()->startOfDay());
    }

    /**
     * Whether this is the org root — the single parentless "DMV" Group at the top of
     * the tree (ADR-0010). Single-sourced on {@see ROOT_SLUG}, the same key the
     * seeders and the top-level ancestor climb resolve the root by.
     */
    public function isRoot(): bool
    {
        return $this->slug === self::ROOT_SLUG;
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
