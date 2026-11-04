<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\DepartureReason;
use App\Enums\DirectoryList;
use App\Enums\Kind;
use App\Enums\RenewalEligibility;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Support\OrgTime;
use Database\Factories\MemberFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Member extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    /**
     * The Records-only fields member administration edits through the record form (ADR-0038 §3).
     * Mass assignable; the rest of {@see RECORDS_FIELDS} is written by the app itself.
     *
     * @var list<string>
     */
    private const RECORDS_EDITABLE = [
        'membership_number',
        'membership_expiry',
        'membership_lifetime',
        'amount_paid',
        'paid_through_year',
        'fee_category',
        'entry_year',
        'service_awards',
        'last_award_year',
        'next_award_year',
        'other_awards',
        'emergency_contact',
        'emergency_contact_number',
        'newsletter_opt_in',
        'code_of_conduct_year',
        'renewal_year',
        'renewal_status',
        'renewal_placements',
        'renewal_other',
        'record_form_placements',
        'record_form_other',
        'mentor_name',
        'volunteer_experience',
        'past_experience',
        'education',
        'photo_public',
    ];

    /**
     * The fields the Records office keeps (ADR-0038 §3): Records-only tier. Hidden from every
     * serialized Member, the shared `auth.user` included; they travel only through
     * `MemberResource::withRecordsFields()`, behind `viewRecordsFields`, so a Member passed whole
     * to a page can never leak one. The status date follows the Category
     * history, Notes only grow by {@see prependNote()}, and sign-in writes the last sign-in.
     *
     * @var list<string>
     */
    public const RECORDS_FIELDS = [
        ...self::RECORDS_EDITABLE,
        'status_date',
        'notes',
        'last_sign_in_at',
    ];

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
        'departure_reason',
        'personal_data_removed_at',
        'locale',
        ...self::RECORDS_EDITABLE,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_by',
        'updated_by',
        'languages',
        'boardPositions',
        ...self::RECORDS_FIELDS,
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
            'departure_reason' => DepartureReason::class,
            'personal_data_removed_at' => 'datetime',
            'super_tier' => 'boolean',
            'support_operator' => 'boolean',
            'no_email' => 'boolean',
            'membership_expiry' => 'date',
            'membership_lifetime' => 'boolean',
            'amount_paid' => 'decimal:2',
            'service_awards' => 'array',
            'newsletter_opt_in' => 'boolean',
            'status_date' => 'date',
            'last_sign_in_at' => 'datetime',
            'photo_public' => 'boolean',
        ];
    }

    /**
     * A Member with no email is a Member the mail cannot reach (ADR-0038 §3). Raising the no-email
     * flag here keeps them out of every Delivery writer, each of which reads the flag and none of
     * which can store a blank address. Filling the address back in leaves the flag for Records.
     */
    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            if (($member->getAttributes()['email'] ?? null) === null) {
                $member->no_email = true;
            }
        });
    }

    /**
     * Prepend a dated, signed line to the Notes log (ADR-0038 §3). The one write to `notes`, so
     * an earlier line is never touched. The date is the museum's day.
     */
    public function prependNote(string $text, Member $author): void
    {
        $line = OrgTime::now()->toDateString().' '.$author->fullName().': '.$text;
        $earlier = $this->getAttributes()['notes'] ?? null;

        $this->notes = filled($earlier) ? $line."\n".$earlier : $line;
    }

    /**
     * Whether this Member must act on the Renewal, and why (#939, ADR-0038 §9). Reads the settings
     * row when none is passed.
     */
    public function renewalEligibility(?RenewalSetting $settings = null): RenewalEligibility
    {
        return RenewalEligibility::for($this, $settings ?? RenewalSetting::current());
    }

    /**
     * The year this Member's Renewal serves (#939, ADR-0038 §9): the settings' renewal year, except
     * from January to March for a Member with no paid-through year or on leave, whose Renewal is
     * for the current year, as the Legacy app has it. The current year stands in until the settings
     * row first names one.
     */
    public function renewalServedYear(?RenewalSetting $settings = null): int
    {
        $settings ??= RenewalSetting::current();
        $now = OrgTime::now();

        if ($now->month < 4 && ($this->paidThroughYear() === null || $this->onRenewalLeave())) {
            return $now->year;
        }

        return $settings->renewal_year ?? $now->year;
    }

    /** Whether the renewal status is LOA: the Member chose leave on their last Renewal. */
    public function onRenewalLeave(): bool
    {
        return strtoupper(trim((string) $this->renewal_status)) === 'LOA';
    }

    /** The paid-through year Records typed, or null. */
    public function paidThroughYear(): ?int
    {
        return $this->paid_through_year === null ? null : (int) $this->paid_through_year;
    }

    /** Whether the code of conduct is accepted for the year the Renewal serves (step 1, #939). */
    public function acceptedCodeOfConduct(?RenewalSetting $settings = null): bool
    {
        return (int) $this->code_of_conduct_year === $this->renewalServedYear($settings);
    }

    /**
     * Renewed, payment pending (#941, ADR-0038 §9): the year last renewed for is ahead of the
     * paid-through year, or there is no paid-through year yet.
     */
    public function paymentPending(): bool
    {
        return $this->renewal_year !== null
            && ($this->paid_through_year === null || $this->paid_through_year < $this->renewal_year);
    }

    /**
     * Who last saved this record (ADR-0036 §8).
     *
     * @return BelongsTo<Member, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'updated_by');
    }

    /**
     * The Member's Category history (ADR-0038 §4).
     *
     * @return HasMany<CategoryChange, $this>
     */
    public function categoryChanges(): HasMany
    {
        return $this->hasMany(CategoryChange::class);
    }

    /**
     * Save an edit of the record as the actor's (ADR-0036 §8): the one way "Last updated by" is
     * written. Saves any pending change on this row and moves `updated_at`, so a change kept
     * outside the row, such as a language or a board position, counts as an edit too.
     */
    public function markUpdatedBy(Member $actor): void
    {
        $this->updated_by = $actor->id;
        $this->touch();
    }

    /**
     * The Member's languages (ADR-0038 §3). Records-only.
     *
     * @return HasMany<MemberLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(MemberLanguage::class);
    }

    /**
     * The board positions the Member has held (ADR-0038 §3). Records-only.
     *
     * @return HasMany<BoardPosition, $this>
     */
    public function boardPositions(): HasMany
    {
        return $this->hasMany(BoardPosition::class);
    }

    /**
     * The Member's rows in the Sign-in log (ADR-0038 §12).
     *
     * @return HasMany<SignIn, $this>
     */
    public function signIns(): HasMany
    {
        return $this->hasMany(SignIn::class);
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
     * The photo URL as the viewer may see it (#922, ADR-0038 §3): null when the Member keeps their
     * photo from peers and the viewer is not the Member, member administration or the super-tier.
     * An unhydrated flag reads as the column default, public.
     */
    public function photoUrlFor(?Member $viewer): ?string
    {
        $url = $this->photo_url;

        if ($url === null || ($this->getAttributes()['photo_public'] ?? true)) {
            return $url;
        }

        return $viewer?->can('viewPhoto', $this) ? $url : null;
    }

    /**
     * The directory roster: Members whose DMV-wide Category grants a listing —
     * Active, Honourary, Sustaining, and LOA (on leave, but still Full access).
     * Excludes the departed (Resigned / Withdrawn / Deceased), the not-yet-activated
     * (PreActive / Provisional) and the non-member standings. The privacy rule has one
     * home here — tunable in a single place, never scattered across controllers.
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
     * The Past Members: the Resigned and Deceased groups once Remove personal data has
     * reached them (ADR-0038 §4, §6).
     *
     * @param  Builder<Member>  $query
     */
    public function scopePast(Builder $query): void
    {
        $query->whereNotNull('personal_data_removed_at')
            ->whereIn('category', Category::departed());
    }

    /**
     * Whether this Member is in the Current Category group: the only standing that may sign in or
     * stay signed in (ADR-0038 §4).
     */
    public function isCurrent(): bool
    {
        return $this->category->group() === CategoryGroup::Current;
    }

    /**
     * The Member's full name, first and last, for a mail's display name and a sender
     * copy's roll of who was not reached (ADR-0024 §3, §4). Trimmed so a missing part
     * leaves no stray space.
     */
    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * The Member's mailing label lines (ADR-0038 §7): name, street, "city, province", then the
     * postal code, which the country leads only outside Canada (the Legacy app's rule).
     *
     * @return list<string>
     */
    public function mailingLabel(): array
    {
        $country = trim((string) $this->address_country);
        $prefix = $country === '' || strcasecmp($country, 'canada') === 0 ? '' : $country.'    ';

        return [
            $this->fullName(),
            (string) $this->address_street,
            trim(implode(', ', array_filter([$this->address_city, $this->address_province], 'filled'))),
            $prefix.$this->address_postal_code,
        ];
    }

    /**
     * The A–Z sort key the roster uses: surname, then given name, lower-cased. A space sorts
     * ahead of any letter, so "Smith" precedes "Smithson".
     */
    public function surnameKey(): string
    {
        return mb_strtolower($this->last_name.' '.$this->first_name);
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
     * Whether this Member belongs to the root DMV Group (ADR-0020 §B, ADR-0036 §9). Root
     * membership is never a stored row: it is derived from the Member's DMV-wide Category —
     * the same {@see Category::grantsDirectoryListing()} standing the Directory (the root's
     * Roster) and the rail's DMV node resolve. The one predicate every root read gate asks.
     */
    public function isRootMember(): bool
    {
        return $this->category->grantsDirectoryListing();
    }

    /**
     * Whether this Member belongs to a Group for a read gate: a stored membership in any
     * other Group, or derived root membership ({@see isRootMember()}) in the root. Read
     * gates that open to "a member of the Group" ask this rather than {@see membershipIn()}
     * so the root — which stores no rows — opens to its derived roster. Standing-specific
     * and role questions keep reading the stored row directly.
     */
    public function isMemberOf(Group $group): bool
    {
        if ($group->isRoot()) {
            return $this->isRootMember();
        }

        return $this->membershipIn($group) !== null;
    }

    /**
     * The Tours this Member may give on a Shift (#790, ADR-0033 §6) — the one "may give" rule
     * every Tour-carrying write reads: of the Shift's offered Tours ({@see Shift::toursOffered()}),
     * those open to all, and those the Member holds an **active** qualification for in the
     * Shift's Group. A Member of another Group holds none there, so open-to-all Tours are all
     * they may give. Empty when the Shift offers no Tours.
     *
     * The Membership's qualifications are loaded once and kept on it, so asking per Shift on a
     * whole Agenda costs one query.
     *
     * @return Collection<int, Tour>
     */
    public function toursGivableOn(Shift $shift): Collection
    {
        $offered = $shift->toursOffered();

        if ($offered->isEmpty()) {
            return $offered;
        }

        $membership = $this->membershipIn($shift->schedule->group);
        $membership?->loadMissing('qualifications');

        $held = $membership === null
            ? collect()
            : $membership->qualifications->where('active', true)->pluck('tour_id');

        return $offered
            ->filter(fn (Tour $tour) => $tour->open_to_all || $held->contains($tour->id))
            ->values();
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
     * Named `administers`, not `isOfficer`: "officer" is broader in GLOSSARY.md than
     * this Secretary-or-Chair predicate.
     */
    public function administers(Group $group): bool
    {
        return $this->canActAs(Role::Secretary, $group)
            || $this->canActAs(Role::Chair, $group);
    }

    /**
     * Whether this Member administers a Group above the given one — {@see administers()} on
     * any strict ancestor, to any depth, never the Group itself (ADR-0036 §11, ADR-0019 §B).
     * The parent-officer authority over a sub-group's structure and roster only: content
     * policies stay on {@see administers()}, so parentage never opens a sub-group's About
     * text, banner, Meetings or Documents. The root and Containers carry no ordinary
     * officers, so the climb skips them rather than let a stray role there cascade. An
     * archived or ended ancestor confers nothing either: its officers lose sub-group
     * authority with it, while an active Group above it keeps theirs.
     */
    public function administersAncestorOf(Group $group): bool
    {
        $this->loadMissing('memberships.roles');

        // Most Members hold no Secretary or Chair role anywhere: answer without the tree.
        $officerRoles = [Role::Secretary, Role::Chair];
        $officerSomewhere = $this->memberships->contains(
            fn (GroupMember $membership) => $membership->roles->contains(fn (GroupMemberRole $row) => in_array($row->role, $officerRoles, true)),
        );
        if (! $officerSomewhere) {
            return false;
        }

        return $group->ancestors()->contains(
            fn (Group $ancestor) => $ancestor->isStructurallyEditable() && $ancestor->isActive() && $this->administers($ancestor),
        );
    }

    /**
     * Whether this Member runs the given Group's roster and may put a sub-group under it: they
     * {@see administers()} it, or a Group above it ({@see administersAncestorOf()}). The roster
     * policy and the `create-sub-group` gate share it.
     */
    public function administersOrOversees(Group $group): bool
    {
        return $this->administers($group) || $this->administersAncestorOf($group);
    }

    /**
     * The Groups this Member administers ({@see administers()}) that head a parent officer's
     * subtrees in DMV Settings (ADR-0036 §11): active ones only, the root and Containers left
     * out, as they carry no ordinary officers. Empty for most Members.
     *
     * @return Collection<int, Group>
     */
    public function administeredGroups(): Collection
    {
        return $this->administeredGroupsQuery()->get();
    }

    /**
     * Whether {@see administeredGroups()} is non-empty: what opens DMV Settings to a parent
     * officer. One exists query, cheap enough for the rail on every request.
     */
    public function administersAnyGroup(): bool
    {
        return $this->administeredGroupsQuery()->exists();
    }

    /**
     * Whether this Member works DMV Settings over the whole org (the super-tier, through the
     * `administer-groups` gate), rather than as a parent officer scoped to their subtrees. The
     * one test every DMV Settings page and form request branches on.
     */
    public function administersWholeOrg(): bool
    {
        return $this->can('administer-groups');
    }

    /**
     * {@see administers()} as a query: active, structurally editable Groups where this Member's
     * membership carries a Secretary or Chair role row (a Chair implies Secretary).
     *
     * @return Builder<Group>
     */
    private function administeredGroupsQuery(): Builder
    {
        return Group::query()
            ->active()
            ->where('slug', '!=', Group::ROOT_SLUG)
            ->where('kind', '!=', Kind::Container->value)
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->where('member_id', $this->getKey())
                ->whereHas('roles', fn (Builder $role) => $role->whereIn('role', [Role::Secretary, Role::Chair])))
            ->orderBy('id');
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
        return $this->stewardMembership(StewardshipFunction::MemberAdmin) !== null;
    }

    /**
     * Whether this Member has applicant authority (ADR-0038 §2): membership, any status and any
     * role, in the Group that stewards `applicants` (Membership today). Such a Member edits the
     * Applicants they mentor and reads the rest; the Applicant Chair ({@see isApplicantChair()})
     * edits all. False when no Group stewards the function. The super-tier inherits it through
     * the `administer-applicants` Gate, not here.
     */
    public function hasApplicantAuthority(): bool
    {
        return $this->stewardMembership(StewardshipFunction::Applicants) !== null;
    }

    /**
     * Whether this Member chairs the Group that stewards `applicants` (ADR-0038 §2): the Applicant
     * Chair, who edits every Applicant and publishes the Applicant code. False when no Group
     * stewards the function.
     */
    public function isApplicantChair(): bool
    {
        return (bool) $this->stewardMembership(StewardshipFunction::Applicants)?->roles->contains('role', Role::Chair);
    }

    /** This Member's membership in the Group stewarding the function, or null (none, or no holder). */
    private function stewardMembership(StewardshipFunction $function): ?GroupMember
    {
        $holder = Group::stewardOf($function);

        return $holder === null ? null : $this->membershipIn($holder);
    }

    /**
     * Whether this Member chairs any active Group (ADR-0038 §2): the reach that lets any Chair read
     * Applicants, to see who is coming to their Group. An archived or expired Group's Chair has
     * none. The same "Chair in an active Group" shape {@see isOrgWideSender()} reads.
     */
    public function chairsAnActiveGroup(): bool
    {
        $this->loadMissing(['memberships.roles', 'memberships.group']);

        return $this->memberships->contains(
            fn (GroupMember $membership): bool => $membership->group->isActive()
                && $membership->roles->contains('role', Role::Chair),
        );
    }

    /**
     * Whether this Member sees every Who's who list on the Directory (#699), not only
     * the open ones ({@see DirectoryList::isOpen()}). As on the old site: Records, and
     * Officers — a Chair, Secretary, Treasurer, or Statistician in any Group. Super-tier
     * sees them too.
     */
    public function seesEveryDirectoryList(): bool
    {
        if ($this->isAllDmv() || $this->hasMemberAdminAuthority()) {
            return true;
        }

        $this->loadMissing('memberships.roles');
        $officerRoles = [Role::Chair, Role::Secretary, Role::Treasurer, Role::Statistician];

        return $this->memberships->contains(
            fn (GroupMember $membership): bool => $membership->roles
                ->contains(fn (GroupMemberRole $role): bool => in_array($role->role, $officerRoles, true)),
        );
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
