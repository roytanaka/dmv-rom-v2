<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\Scope;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;
use Database\Factories\GroupFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The curated demo data (PRD #139): a believable slice of the real DMV org used
 * to populate staging by hand before a board pitch. Unlike {@see OrgTreeSeeder}
 * — the test-only fixture built from per-slice factories — this seeder is
 * faker-free: every row is created with plain Eloquent, because the factories
 * call `fake()`, a dev-only dependency absent from the `--no-dev` staging build
 * (mirroring how {@see DatabaseSeeder} creates its known login directly).
 *
 * The curated org tree below is the single source of truth (PRD #139): the real
 * DMV committees, programs, working groups, cohorts and projects, transcribed
 * once from the maintainer's handoff comment and living here and nowhere else —
 * not in any markdown doc, CONTEXT.md, or data file. Changing the org means
 * editing this seeder. Each node is created with `firstOrCreate` keyed on its
 * slug, so re-running heals rather than duplicates.
 *
 * Mapping notes for the transcription:
 * - The source groups its top level into sections (Governance & Operations,
 *   Programs, Special Projects) plus the standalone Friends-of committees. The
 *   {@see Kind} taxonomy has no dedicated "section/division" case, so those
 *   org-level container sections are modelled as organization-scoped
 *   {@see Kind::StandingCommittee} nodes.
 * - {@see Scope} and the capability flags are derived from each node's Kind to
 *   match the conventions encoded in {@see GroupFactory}.
 * - Archived exhibition cohorts carry {@see LifecycleState::Archived}; the
 *   active-but-stale event pool (OSIRIS REX VOLUNTEERS, never closed out) stays
 *   lifecycle-Active but its time-boxed window has lapsed, so the lifecycle
 *   filter ({@see Group::scopeActive()}) still catches it as a dead pool.
 * - ROMWalks appears twice in the source comment with differing children; it is
 *   transcribed once as the union of both listings (the second is a superset of
 *   the first plus "Brochure Committee").
 *
 * It is deliberately NOT wired into {@see DatabaseSeeder} and not part of any
 * deploy step — it runs only by hand. The public constants below are stable
 * handles for tests, mirroring {@see OrgTreeSeeder}.
 */
class DemoSeeder extends Seeder
{
    public const ROOT = 'dmv';

    public const COMMITTEE = 'governance-operations';

    public const RECORDS = 'records';

    public const PROGRAM = 'docents';

    public const RECEPTION = 'reception';

    public const CHAIR_EMAIL = 'demo.chair@dmv.test';

    public const COORDINATOR_EMAIL = 'demo.coordinator@dmv.test';

    /**
     * Slugs created so far this run — a guard so two curated nodes that slugify
     * to the same value fail loudly instead of silently merging via firstOrCreate.
     *
     * @var array<string, true>
     */
    private array $seenSlugs = [];

    public function run(): void
    {
        $this->seenSlugs = [];
        $this->build($this->tree(), null, 0);
        $this->roster();
    }

    /**
     * A curated demo roster (PRD #139, slice 3 / #142): a believable spread of
     * people across the tree so the board demo shows the app modelling how DMV
     * actually works. It spans varied membership statuses (Full, Trainee, an LOA
     * with a window set, plus Emeritus and Transitional for breadth), the
     * capability-backed roles on the scheduling/documenting/stats-tracking
     * Docents program (Scheduler, Librarian, Statistician — each gated by a
     * Group flag), the core Chair and Secretary roles, and the Records Group
     * stewarding `member_admin`.
     *
     * Like the rest of this seeder it is faker-free (plain Eloquent) and
     * idempotent: members key on email, memberships on the (Group, Member) pair,
     * and roles/stewardships heal rather than duplicate on re-run. The slice is
     * deliberately small (~30 rows) — realistic full-roster volume is out of
     * scope for v1.
     */
    private function roster(): void
    {
        $root = Group::where('slug', self::ROOT)->firstOrFail();
        $committee = Group::where('slug', self::COMMITTEE)->firstOrFail();
        $records = Group::where('slug', self::RECORDS)->firstOrFail();
        $program = Group::where('slug', self::PROGRAM)->firstOrFail();
        $reception = Group::where('slug', self::RECEPTION)->firstOrFail();

        // Governance — core roles, which attach to any Group regardless of flags.
        $this->membership($root, $this->member(self::CHAIR_EMAIL, 'Demo Chair'), MembershipStatus::Full, [Role::Chair]);
        $this->membership($committee, $this->member('demo.secretary@dmv.test', 'Demo Secretary'), MembershipStatus::Full, [Role::Secretary]);
        $this->membership($committee, $this->member('demo.treasurer@dmv.test', 'Demo Treasurer'), MembershipStatus::Full, [Role::Treasurer]);

        // The Docents program — its capability flags back the roles below.
        $this->membership($program, $this->member(self::COORDINATOR_EMAIL, 'Demo Coordinator'), MembershipStatus::Full, [Role::Scheduler]);
        $this->membership($program, $this->member('demo.librarian@dmv.test', 'Demo Librarian'), MembershipStatus::Full, [Role::Librarian]);
        $this->membership($program, $this->member('demo.statistician@dmv.test', 'Demo Statistician'), MembershipStatus::Full, [Role::Statistician]);
        $this->membership($program, $this->member('demo.trainee@dmv.test', 'Demo Trainee'), MembershipStatus::Trainee);
        $this->loaMembership($program, $this->member('demo.onleave@dmv.test', 'Demo On Leave'));

        // A second program, for roster breadth and varied standings.
        $this->membership($reception, $this->member('demo.greeter@dmv.test', 'Demo Greeter'), MembershipStatus::Full);
        $this->membership($reception, $this->member('demo.emeritus@dmv.test', 'Demo Emeritus'), MembershipStatus::Emeritus);
        $this->membership($reception, $this->member('demo.transitional@dmv.test', 'Demo Transitional'), MembershipStatus::Transitional);

        // The Records Group stewards member administration (ADR-0011): authority
        // is membership in it, not a standalone flag.
        $this->membership($records, $this->member('demo.clerk@dmv.test', 'Demo Clerk'), MembershipStatus::Full);
        $this->steward($records, StewardshipFunction::MemberAdmin);
    }

    /**
     * The curated DMV org tree — the single source of truth (PRD #139). Built
     * from the node helpers below so the shape reads like the source comment.
     *
     * @return array<string, mixed>
     */
    private function tree(): array
    {
        return [
            'name' => 'Department of Museum Volunteers',
            'slug' => self::ROOT,
            'kind' => Kind::StandingCommittee,
            'description' => 'The DMV at large — the root of the org tree.',
            'children' => [
                // Governance & Operations — the org-level standing committees.
                $this->sc('Governance & Operations', [
                    $this->sc('Associated Friends'),
                    $this->sc('Awards'),
                    $this->sc("Chairs' Corner"),
                    $this->sc('Communications'),
                    $this->sc('DEI Committee'),
                    $this->sc('Endowments'),
                    $this->sc('Executive'),
                    $this->sc('First Magnitude'),
                    $this->sc('Governance'),
                    $this->sc('Health & Safety'),
                    $this->sc('Membership'),
                    $this->sc('Nominations'),
                    $this->sc('Records'),
                    $this->sc('Social'),
                    $this->sc('System Services'),
                ]),
                // Programs — the member-facing operating units, with their
                // working groups and exhibition cohorts.
                $this->sc('Programs', [
                    $this->program('Docents', [
                        $this->cohort('Pompeii', archived: true),
                        $this->cohort('Ultimate Dinosaurs', archived: true),
                        $this->cohort('Forbidden City', archived: true),
                    ]),
                    $this->program('Guides du ROM'),
                    $this->program('Les Amis Francophiles'),
                    $this->program('DMV Hands-on Tours', [
                        $this->workingGroup('Social', 'hands-on-tours-social'),
                        $this->workingGroup('Training', 'hands-on-tours-training'),
                        $this->workingGroup('Vetting'),
                    ]),
                    $this->program('Gallery Interpreters'),
                    $this->program('ROMForYou', [
                        $this->workingGroup('Content Development'),
                        $this->workingGroup('Team Leads — adult presentations'),
                        $this->workingGroup('Outreach'),
                        $this->workingGroup('Adapted Presentations'),
                    ]),
                    $this->program('Visitor Guides'),
                    $this->program('Visitor Wayfinders', [
                        $this->workingGroup('Documentation'),
                        $this->workingGroup('Shadow Shift & Vetting Volunteers'),
                        $this->workingGroup('Social Committee'),
                        $this->cohort('OSIRIS REX VOLUNTEERS', stale: true),
                        $this->cohort('TRex Spot Tours', archived: true),
                        $this->cohort('Blue Whale', archived: true),
                        $this->cohort('Zuul', archived: true),
                    ]),
                    // ROMWalks — one coherent subtree merged from the source's
                    // two differing listings (see class docblock).
                    $this->program('ROMWalks', [
                        $this->workingGroup('Brochure Committee'),
                        $this->workingGroup('Education'),
                        $this->workingGroup('PR Committee'),
                        $this->workingGroup('Script Vetting'),
                        $this->workingGroup('Statistical'),
                        $this->workingGroup('Training', 'romwalks-training'),
                        $this->workingGroup('Walker Vetting'),
                    ]),
                    $this->program('Reception', [
                        $this->workingGroup('Library'),
                    ]),
                    $this->program('ROMBus'),
                    $this->program('ROMTravel', [
                        $this->workingGroup('Admin Committee'),
                        $this->workingGroup('Feasibility Committee'),
                        $this->workingGroup('Support Roles'),
                    ]),
                ]),
                // Special Projects — the cross-program project node.
                $this->sc('Special Projects', [
                    $this->project('Auschwitz: Exhibition/ Tours'),
                    $this->project('Burton Lim Fieldnotes'),
                    $this->project('DMV Archive Inventory'),
                    $this->project('Lady-Bird Beetle'),
                    $this->project('Osiris Rex Return'),
                    $this->project('Palaeo Field Notes'),
                    $this->project('ROM eBird Records'),
                    $this->project('Transcribe interview tapes'),
                ]),
                // Friends-of standing committees (some with their own sub-groups).
                $this->sc('Bishop White (FEA)'),
                $this->sc('Friends of Global South Asia (FSA)'),
                $this->sc('Friends of Textiles & Costume', [
                    $this->workingGroup('Adopt-a-Journal'),
                    $this->workingGroup('Donor Friends'),
                    $this->workingGroup('Education SubCommittee'),
                    $this->workingGroup('Newsletter SubCommittee'),
                    $this->workingGroup('Programs & Events'),
                ]),
                $this->sc('Friends of Palaeontology (FOP)', [
                    $this->workingGroup('Vertebrate Palaeontology'),
                ]),
                $this->sc('Friends of Earth & Space (FES)'),
            ],
        ];
    }

    /**
     * A standing-committee node (also used for the org-level container sections).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function sc(string $name, array $children = []): array
    {
        return ['name' => $name, 'kind' => Kind::StandingCommittee, 'children' => $children];
    }

    /**
     * A program node.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function program(string $name, array $children = []): array
    {
        return ['name' => $name, 'kind' => Kind::Program, 'children' => $children];
    }

    /**
     * A working-group node. Pass an explicit slug to disambiguate names that
     * recur across the tree (e.g. "Training" under two programs).
     *
     * @return array<string, mixed>
     */
    private function workingGroup(string $name, ?string $slug = null): array
    {
        $node = ['name' => $name, 'kind' => Kind::WorkingGroup];

        if ($slug !== null) {
            $node['slug'] = $slug;
        }

        return $node;
    }

    /**
     * A cohort node. `archived` marks a closed exhibition cohort; `stale` marks
     * an active pool whose time-boxed window has lapsed (never closed out).
     *
     * @return array<string, mixed>
     */
    private function cohort(string $name, bool $archived = false, bool $stale = false): array
    {
        return ['name' => $name, 'kind' => Kind::Cohort, 'archived' => $archived, 'stale' => $stale];
    }

    /**
     * A project node.
     *
     * @return array<string, mixed>
     */
    private function project(string $name): array
    {
        return ['name' => $name, 'kind' => Kind::Project];
    }

    /**
     * Recursively create a node and its children, assigning sibling order by
     * position. Returns the created (or pre-existing) Group.
     *
     * @param  array<string, mixed>  $node
     */
    private function build(array $node, ?Group $parent, int $order): Group
    {
        $slug = $node['slug'] ?? Str::slug($node['name']);

        if (isset($this->seenSlugs[$slug])) {
            throw new RuntimeException("Duplicate demo Group slug \"{$slug}\" — disambiguate the curated tree.");
        }
        $this->seenSlugs[$slug] = true;

        $group = $this->group($slug, $this->attributesFor($node, $parent?->id, $order));

        foreach (array_values($node['children'] ?? []) as $i => $child) {
            $this->build($child, $group, $i);
        }

        return $group;
    }

    /**
     * Build the full attribute set for a node. Scope, capability flags and
     * time-boxing are derived from the node's Kind (matching GroupFactory),
     * with lifecycle/window overrides for archived and stale nodes.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function attributesFor(array $node, ?int $parentId, int $order): array
    {
        $kind = $node['kind'];

        $attributes = [
            'parent_id' => $parentId,
            'name' => $node['name'],
            'description' => $node['description'] ?? null,
            'kind' => $kind,
            'scope' => $this->scopeFor($kind),
            'display_order' => $order,
            'lifecycle_state' => LifecycleState::Active,
            'time_boxed' => false,
            'start_date' => null,
            'end_date' => null,
        ] + $this->capabilitiesFor($kind);

        // Time-boxed Kinds default to an open window (started a while ago, still open).
        if (in_array($kind, [Kind::Project, Kind::Cohort], true)) {
            $attributes['time_boxed'] = true;
            $attributes['start_date'] = now()->subMonths(6)->toDateString();
            $attributes['end_date'] = now()->addMonths(6)->toDateString();
        }

        if ($node['archived'] ?? false) {
            $attributes['lifecycle_state'] = LifecycleState::Archived;
            $attributes['time_boxed'] = true;
            $attributes['start_date'] = now()->subYears(2)->toDateString();
            $attributes['end_date'] = now()->subYear()->toDateString();
        } elseif ($node['stale'] ?? false) {
            // Active-but-stale: never archived, but the window lapsed, so the
            // active-Groups filter still excludes it as a dead pool.
            $attributes['time_boxed'] = true;
            $attributes['start_date'] = now()->subYears(2)->toDateString();
            $attributes['end_date'] = now()->subMonths(6)->toDateString();
        }

        return $attributes;
    }

    private function scopeFor(Kind $kind): Scope
    {
        return match ($kind) {
            Kind::StandingCommittee => Scope::Organization,
            Kind::Program, Kind::Cohort => Scope::Program,
            Kind::WorkingGroup, Kind::Project => Scope::Subteam,
        };
    }

    /**
     * Capability flags per Kind, mirroring the per-Kind states in GroupFactory.
     *
     * @return array<string, bool>
     */
    private function capabilitiesFor(Kind $kind): array
    {
        $off = [
            'has_meetings' => false,
            'has_documents' => false,
            'has_scheduling' => false,
            'has_content_catalog' => false,
            'has_vetting' => false,
            'has_hours_stats' => false,
        ];

        return match ($kind) {
            Kind::StandingCommittee => ['has_meetings' => true, 'has_documents' => true] + $off,
            Kind::Program => [
                'has_documents' => true,
                'has_scheduling' => true,
                'has_content_catalog' => true,
                'has_hours_stats' => true,
            ] + $off,
            Kind::WorkingGroup => ['has_meetings' => true] + $off,
            Kind::Project => ['has_documents' => true] + $off,
            Kind::Cohort => ['has_scheduling' => true] + $off,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function group(string $slug, array $attributes): Group
    {
        return Group::firstOrCreate(['slug' => $slug], $attributes);
    }

    /** Not via the factory: factories call fake(), absent from the --no-dev build. */
    private function member(string $email, string $name): Member
    {
        // super_tier is not mass-assignable (#153) and defaults to false in the
        // schema. email_verified_at is likewise non-fillable: forceFill marks the
        // demo member verified once, idempotently.
        $member = Member::firstOrCreate(['email' => $email], [
            'name' => $name,
            'category' => Category::Active,
            'password' => Hash::make('password'),
        ]);

        if ($member->email_verified_at === null) {
            $member->forceFill(['email_verified_at' => now()])->save();
        }

        return $member;
    }

    /**
     * Ensure a membership exists for the Member in the Group with the given
     * status, carrying the given roles (each attached once). Keyed on the
     * (Group, Member) pair so re-running heals rather than duplicates.
     *
     * @param  list<Role>  $roles
     */
    private function membership(Group $group, Member $member, MembershipStatus $status, array $roles = []): GroupMember
    {
        $membership = GroupMember::firstOrCreate(
            ['group_id' => $group->id, 'member_id' => $member->id],
            ['status' => $status],
        );

        foreach ($roles as $role) {
            $membership->roles()->firstOrCreate(['role' => $role]);
        }

        return $membership;
    }

    /** A membership on an open LOA window (started a month ago, ends a month out). */
    private function loaMembership(Group $group, Member $member): GroupMember
    {
        return GroupMember::firstOrCreate(
            ['group_id' => $group->id, 'member_id' => $member->id],
            [
                'status' => MembershipStatus::Loa,
                'loa_start' => now()->subMonth()->toDateString(),
                'loa_end' => now()->addMonth()->toDateString(),
            ],
        );
    }

    /** Ensure the Group stewards the given org-wide function, without duplicating. */
    private function steward(Group $group, StewardshipFunction $function): GroupStewardship
    {
        return GroupStewardship::firstOrCreate(
            ['group_id' => $group->id, 'function' => $function],
        );
    }
}
