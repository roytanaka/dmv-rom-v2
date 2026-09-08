<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Category;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use Database\Factories\MemberFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Member extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'business_phone',
        'address_street',
        'address_city',
        'address_province',
        'address_postal_code',
        'address_country',
        'password',
        'category',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Derived attributes appended to array/JSON form. `photo_url` rides along on the
     * shared `auth.user` prop so the frontend renders the avatar from a ready static
     * URL and never reconstructs the public-disk path itself.
     *
     * @var list<string>
     */
    protected $appends = [
        'photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'category' => Category::class,
            'super_tier' => 'boolean',
            'support_operator' => 'boolean',
            'no_email' => 'boolean',
        ];
    }

    /**
     * The public URL of the member's profile photo, or null when none is set. The
     * photo lane is public (webserver-served via `storage:link`), so this is a plain
     * static URL — no gated controller round-trip (contrast the Documents lane,
     * ADR-0003 + amendment). Callers render it directly into an avatar image slot.
     *
     * @return Attribute<?string, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            // Read the raw attribute rather than $this->photo_path: as an appended
            // accessor this runs during serialization, and under strict-mode
            // (preventAccessingMissingAttributes) a not-yet-hydrated column would throw
            // — a freshly factory-made model has no photo_path key until it round-trips.
            get: function (): ?string {
                $path = $this->attributes['photo_path'] ?? null;

                return $path ? Storage::disk('public')->url($path) : null;
            },
        );
    }

    /**
     * The directory roster: Members whose DMV-wide Category grants a listing —
     * Active, Honourary, Sustaining, and LOA (on leave, but still Full access).
     * Excludes the departed (Resigned / Withdrawn / Deceased) and the not-yet-
     * activated (PreActive / Provisional). The privacy rule has one home here —
     * tunable in a single place, never scattered across controllers.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeInDirectory(Builder $query): void
    {
        $query->whereIn('category', array_filter(
            Category::cases(),
            fn (Category $category) => $category->grantsDirectoryListing(),
        ));
    }

    /**
     * The locale system-generated messages address this Member in (Laravel's
     * {@see HasLocalePreference}). The mailer reads it to render each recipient's copy of a
     * notification in their own language — the Sign-up cancellation email is the first
     * reader (#358) — so a French Scheduler and an English one are each written to correctly
     * from one send loop. Falls back to the app locale when the column is unset.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Whether this Member holds the single org-wide "all-DMV" access grant.
     *
     * This is the only authority that reaches across the whole DMV; every other
     * grant is scoped to a Group via the membership pivot (later spine slices).
     */
    public function isAllDmv(): bool
    {
        return $this->super_tier;
    }

    /**
     * Whether this Member holds support-operator access — the maintainer power to
     * impersonate a volunteer for support/QA (ADR-0009). This is deliberately
     * *separate* from {@see isAllDmv()}: org authority (President / VPs) and operator
     * access (the engineer) are different principals, and conflating them into one
     * `super_tier` flag is the smell this split removes. A super-tier executive is
     * NOT an operator unless independently marked one; least privilege.
     *
     * Read directly, never as a Gate ability, on purpose: the `Gate::before`
     * super-tier short-circuit (AppServiceProvider) grants super-tier every ability,
     * so a Gate-backed check would silently hand impersonation back to the President —
     * exactly the conflation being undone. The genuinely-above-President access
     * (DB, migrations, deploy) lives outside the app entirely and is not modelled here.
     */
    public function isSupportOperator(): bool
    {
        return $this->support_operator;
    }

    /**
     * Every membership this Member holds, one per Group they belong to.
     *
     * @return HasMany<GroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Fetch this Member's membership in a given Group, or null if they aren't a
     * member of it. Standing is per-Group, so this is the entry point every
     * Group-scoped authorization decision reads.
     *
     * Resolves against the `memberships` relation collection, which loads once
     * and then resolves in memory — so a gate/policy that asks repeated
     * authorization questions about one Member (or eager-loads
     * `memberships.roles`) never re-queries per call.
     *
     * Self-loads `memberships.roles` if absent so every downstream role check
     * ({@see canActAs}, {@see holdsRole}, {@see administers}) is safe under strict
     * mode's lazy-load guard, whatever the caller pre-loaded. `loadMissing` is a
     * no-op once loaded — the read controllers still eager-load at the boundary, so
     * this only catches the paths (e.g. a write's Form Request `authorize()`) that
     * reach a policy before the actor's roles were hydrated. Without it a Chair /
     * Secretary 500s where the super-tier `Gate::before` short-circuit hides the gap.
     */
    public function membershipIn(Group $group): ?GroupMember
    {
        $this->loadMissing('memberships.roles');

        return $this->memberships->firstWhere('group_id', $group->getKey());
    }

    /**
     * Whether this Member literally holds the given role in the given Group —
     * the "is an X" check for display. Authorization decisions call
     * {@see canActAs()} instead, which also folds in Chair-implication. False
     * when the Member isn't in the Group.
     */
    public function holdsRole(Role $role, Group $group): bool
    {
        return $this->membershipIn($group)
            ?->roles->contains('role', $role) ?? false;
    }

    /**
     * Whether this Member can act with the authority of the given role in the
     * given Group — the single resolver every gate and policy calls. Unlike
     * {@see holdsRole()}, this folds in Chair-implication: a Chair implies every
     * officer role within its *own* Group except `Treasurer`, whose finance
     * authority requires the explicit role (separation of duties). Authority
     * never leaks across Groups — a role held in Group A grants nothing in
     * Group B.
     *
     * Matches group-wide role rows only. There is no subdivision column yet;
     * when ADR-0010's subdivision-scoped roles land, this keeps matching
     * group-wide rows and the narrowing lives in the consuming capability's own
     * policy — the seam is preserved here, not built.
     */
    public function canActAs(Role $role, Group $group): bool
    {
        $membership = $this->membershipIn($group);

        if ($membership === null) {
            return false;
        }

        if ($membership->roles->contains('role', $role)) {
            return true;
        }

        return $role !== Role::Treasurer
            && $membership->roles->contains('role', Role::Chair);
    }

    /**
     * Whether this Member administers the given Group — the core officer authority
     * over its roster, Overview, and meetings, held by a Secretary or Chair (Chair
     * folded in by {@see canActAs()}). The single predicate the Group, group-member,
     * and meeting policies share, so "who runs this Group" is stated once here rather
     * than re-spelled as `canActAs(Secretary) || canActAs(Chair)` at each call site.
     *
     * Group-scoped like every role check: administering Group A grants nothing in
     * Group B. Capability-specific powers (e.g. meetings) still add their own guard
     * at the policy — this answers the officer question only, not the capability one.
     * Named `administers`, not `isOfficer`: "officer" is broader in CONTEXT.md than
     * this Secretary-or-Chair predicate.
     */
    public function administers(Group $group): bool
    {
        return $this->canActAs(Role::Secretary, $group)
            || $this->canActAs(Role::Chair, $group);
    }

    /**
     * Whether this Member may post to the org-wide news feed at all — i.e. can act
     * as news-editor of any announcements-on Group they belong to (ADR-0017 §5).
     * The coarse hint behind the feed's "New post" control; the per-item edit/
     * delete decisions still run through the NewsPolicy. Super-tier passes via the
     * `Gate::before` short-circuit on the `post-news` gate, not through here.
     */
    public function canPostNews(): bool
    {
        return $this->memberships->contains(
            fn (GroupMember $membership) => $membership->group->has_announcements
                && $this->canActAs(Role::NewsEditor, $membership->group)
        );
    }

    /**
     * The catalog skills this Member is willing to use for DMV/ROM (PRD #243). A
     * plain `belongsToMany` over the bare `member_skill` pivot: selection is a flat
     * multi-select with no per-skill state, so — unlike the roles/status the
     * explicit-pivot {@see GroupMember} model carries — nothing rides on the pivot.
     *
     * Records-only: skills are confidential and never surface on any peer-visible
     * payload (never added to MemberResource); they inform leadership's role and
     * project suggestions, not peers.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /**
     * Whether this Member holds member-administration authority — i.e. is a member
     * of the Group that stewards `member_admin` (the Records Group). Authority is
     * explicit and per-Group (ADR-0011): reach over member administration comes
     * from membership in the stewarding Group, never a standalone flag. False when
     * no Group stewards the function.
     */
    public function hasMemberAdminAuthority(): bool
    {
        $records = Group::stewardOf(StewardshipFunction::MemberAdmin);

        return $records !== null && $this->membershipIn($records) !== null;
    }

    /**
     * Whether this Member is one of the given Group's officers — holds at least one
     * {@see Role} in it (ADR-0024 §5: "the Group's officers means memberships holding
     * any Role, not a fixed subset"). Distinct from {@see administers()}, the narrower
     * Secretary-or-Chair predicate; a Scheduler or Librarian is an officer here but
     * does not administer. The gate behind the officer-only Broadcast Audiences (the
     * officer set and a child Group's roster). False when the Member isn't in the Group.
     */
    public function isOfficerOf(Group $group): bool
    {
        return (bool) $this->membershipIn($group)?->roles->isNotEmpty();
    }

    /**
     * Whether this Member may send the org-wide Broadcast Audiences (ADR-0024 §5) —
     * the two ways to hold the franchise: membership in any Group that stewards
     * `org_mail` (the Executive, Records, and Awards Groups), or a Chair role in any
     * *active* Group at any depth. The gate the Directory's org-wide Audiences and its
     * hand-pick read; the three leadership Audiences (Board, Committee Chairs, All
     * Chairs) are open to every Member and do not consult this.
     *
     * Self-loads the roles and stewardships it reads so it is safe under strict mode
     * whatever the caller pre-loaded, mirroring {@see membershipIn()}.
     */
    public function isOrgWideSender(): bool
    {
        $this->loadMissing(['memberships.roles', 'memberships.group.stewardships']);

        return $this->memberships->contains(function (GroupMember $membership): bool {
            $group = $membership->group;

            return $group->stewardships->contains('function', StewardshipFunction::OrgMail)
                || ($group->isActive() && $membership->roles->contains('role', Role::Chair));
        });
    }
}
