<?php

namespace Database\Seeders;

use App\Enums\AccessTier;
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
use App\Personas\Persona;
use App\Personas\PersonaCatalogue;
use App\Support\ProfilePhotoStorage;
use Database\Factories\GroupFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
 * It is the curated payload of {@see DatabaseSeeder}: every `db:seed` (local
 * `db:fresh` and the staging deploy's `migrate:fresh --seed`) runs it after the
 * ordinary-member login. The super-tier trio the role-switcher operates from
 * lives in the {@see PersonaCatalogue} and is seeded here (#221). The public
 * constants below are stable handles for tests, mirroring {@see OrgTreeSeeder}.
 */
class DemoSeeder extends Seeder
{
    public const ROOT = 'dmv';

    public const COMMITTEE = 'governance-operations';

    public const RECORDS = 'records';

    public const PROGRAM = 'docents';

    public const RECEPTION = 'reception';

    // Historical stable handles, now sourced from the persona catalogue (the
    // single source of truth for persona identities) so list and seed can't drift.
    public const CHAIR_EMAIL = PersonaCatalogue::CHAIR_EMAIL;

    public const COORDINATOR_EMAIL = PersonaCatalogue::SCHEDULER_EMAIL;

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
        $this->bulkRoster();
    }

    /**
     * The curated persona roster (PRD #220 / #221): every catalogued Persona from
     * {@see PersonaCatalogue}, seeded into its Group placements. The catalogue is
     * the single source of truth — this method computes no identities, standings
     * or roles of its own, so the seeded set and the switcher's picker can never
     * drift. It spans the support operator (the switcher's runner), the super-tier
     * trio, the core officer roles, the
     * capability-backed roles (each on a Group whose flag is on), varied membership
     * standings, and the no-authority negatives.
     *
     * Like the rest of this seeder it is faker-free (plain Eloquent) and
     * idempotent: members key on email, memberships on the (Group, Member) pair,
     * and roles/stewardships heal rather than duplicate on re-run.
     */
    private function roster(): void
    {
        foreach (PersonaCatalogue::all() as $persona) {
            $member = $this->personaMember($persona);

            foreach ($persona->placements as $placement) {
                $group = Group::where('slug', $placement->groupSlug)->firstOrFail();

                if ($placement->status === MembershipStatus::Loa) {
                    $this->loaMembership($group, $member);
                } else {
                    $this->membership($group, $member, $placement->status, $placement->roles);
                }
            }
        }

        // The Records Group stewards member administration (ADR-0011): authority
        // is membership in it, not a standalone flag. Org structure, not a Persona.
        $records = Group::where('slug', self::RECORDS)->firstOrFail();
        $this->steward($records, StewardshipFunction::MemberAdmin);
    }

    /**
     * Create (or heal) the Member behind a catalogued Persona. category is fillable
     * but re-set here so a catalogue change lands on re-seed; super_tier,
     * support_operator and email_verified_at are non-fillable (#153, ADR-0017 §1),
     * force-filled the same way {@see DatabaseSeeder} grants them so neither the
     * super-tier trio nor the operator marker can be set through any form.
     */
    private function personaMember(Persona $persona): Member
    {
        $member = Member::firstOrCreate(['email' => $persona->email], [
            'first_name' => $persona->firstName,
            'last_name' => $persona->lastName,
            'category' => $persona->category,
            'password' => Hash::make('password'),
        ]);

        $member->category = $persona->category;
        $member->forceFill([
            'super_tier' => $persona->superTier,
            'support_operator' => $persona->operator,
            'email_verified_at' => $member->email_verified_at ?? now(),
        ])->save();

        // Named personas always get a photo so the Directory and Roster show faces.
        $this->seedPhoto($member);

        return $member;
    }

    /**
     * Fetch a stable DiceBear avatar for the Member and store it via the ordinary
     * public-disk/UUID pipeline, setting `photo_path`. The seed is the email, so the
     * same persona always gets the same face across reseeds.
     *
     * Best-effort: a network failure, timeout, or rate-limit falls back to no photo
     * (initials) and never fails the seed. Idempotent — skips already-photographed
     * members so a reseed neither refetches nor orphans a second file.
     */
    private function seedPhoto(Member $member): void
    {
        if ($member->photo_path !== null) {
            return;
        }

        try {
            $response = Http::timeout(self::PHOTO_TIMEOUT)
                ->get(sprintf(self::DICEBEAR_URL, rawurlencode($member->email)));
        } catch (Throwable) {
            return;
        }

        if (! $response->successful() || $response->body() === '') {
            return;
        }

        $member->photo_path = (new ProfilePhotoStorage)->putWebp($response->body());
        $member->save();
    }

    /** How many generated volunteers populate the bulk roster. */
    private const POOL_SIZE = 60;

    /**
     * Photograph 1-in-N generated volunteers: a realistic mix that shows the
     * initials fallback, and modest request volume so the endpoint isn't hammered
     * on each staging deploy's `migrate:fresh --seed`.
     */
    private const PHOTO_EVERY = 3;

    /**
     * The DiceBear HTTP avatar endpoint (v10.x): a stable, per-seed WebP. DiceBear
     * core is MIT; the "lorelei" style is CC BY 4.0 — attribution "Avatars by
     * DiceBear, Lorelei by Lisa Wischofsky, CC BY 4.0" — fine for internal demo seed
     * data. `%s` is the URL-encoded seed (the email), `size=512` matches the photo
     * pipeline's square target so the bytes drop in without re-processing.
     */
    private const DICEBEAR_URL = 'https://api.dicebear.com/10.x/lorelei/webp?size=512&seed=%s';

    /** Seconds to wait on each best-effort avatar fetch before giving up to initials. */
    private const PHOTO_TIMEOUT = 5;

    /**
     * A populated demo roster on top of the curated handful above: ~60 generated
     * volunteers spread across every Group so each Group page shows a believable
     * roster, plus the org-wide rule that the root DMV Group's roster is *everyone*
     * (the top-level Group every volunteer belongs to). Like the rest of this
     * seeder it is faker-free (a deterministic curated name pool, not fake()) so it
     * runs under the --no-dev staging build, and idempotent (members key on email,
     * memberships on the (Group, Member) pair), so re-seeding heals not duplicates.
     */
    private function bulkRoster(): void
    {
        $pool = $this->memberPool();
        $this->distribute($pool);
        $this->enrollEveryoneInRoot();
    }

    /**
     * Create (or load) the generated volunteer pool — deterministic so re-running
     * lands the same people. Names pair the two curated lists so every full name is
     * unique across the pool: the first name cycles each lap while the last name is
     * bumped one step per lap, so a second lap never repeats a first-lap pairing
     * (the pool is larger than either list). Email carries the index for stability.
     *
     * @return list<Member>
     */
    private function memberPool(): array
    {
        $first = $this->firstNames();
        $last = $this->lastNames();

        $pool = [];

        for ($i = 0; $i < self::POOL_SIZE; $i++) {
            $lap = intdiv($i, count($first));
            $name = $first[$i % count($first)].' '.$last[($i * 7 + $lap) % count($last)];
            $email = sprintf('%s.%s%02d@dmv.test', Str::lower(Str::before($name, ' ')), Str::lower(Str::after($name, ' ')), $i);

            $member = $this->member($email, $name);

            // A spread of DMV-wide Categories for directory/standing variety; most
            // are Active. forceFill: phone is fillable but only ever set here for the
            // pool, so heal it on the same idempotent pass as the category.
            $member->forceFill([
                'phone' => sprintf('416-555-%04d', $i),
                'category' => $this->categoryFor($i),
            ])->save();

            if ($i % self::PHOTO_EVERY === 0) {
                $this->seedPhoto($member);
            }

            $pool[] = $member;
        }

        return $pool;
    }

    /**
     * Spread the pool across every non-root Group: each Group gets a deterministic
     * slice of 4–7 people, the first two carrying the core Chair and Secretary roles
     * so the Overview's "leadership at a glance" populates everywhere. Offsets are
     * coprime-ish to the pool size so slices overlap (people sit on several Groups,
     * as in real life) without any Group coming up empty.
     *
     * @param  list<Member>  $pool
     */
    private function distribute(array $pool): void
    {
        $n = count($pool);

        $groups = Group::where('slug', '!=', self::ROOT)->orderBy('id')->get();

        foreach ($groups as $gi => $group) {
            $size = 4 + ($gi % 4);

            for ($k = 0; $k < $size; $k++) {
                $idx = ($gi * 5 + $k * 13) % $n;
                $roles = match ($k) {
                    0 => [Role::Chair],
                    1 => [Role::Secretary],
                    default => [],
                };

                $this->membership($group, $pool[$idx], $this->statusFor($idx), $roles);
            }
        }
    }

    /**
     * Every Member belongs to the root DMV Group — its roster is the whole DMV.
     * Heals existing memberships (e.g. the curated Chair) rather than duplicating.
     */
    private function enrollEveryoneInRoot(): void
    {
        $root = Group::where('slug', self::ROOT)->firstOrFail();

        Member::query()->each(function (Member $member) use ($root) {
            // Departed Members (Resigned / Withdrawn / Deceased) are exempt: the
            // departed Persona must not pick up a Full root membership, so it
            // exercises the My-Groups standing-exclusion axis, not just Directory.
            if ($member->category->accessTier() === AccessTier::None) {
                return;
            }

            $this->membership($root, $member, MembershipStatus::Full);
        });
    }

    private function categoryFor(int $i): Category
    {
        return match ($i % 8) {
            5 => Category::Honourary,
            6 => Category::Sustaining,
            7 => Category::Loa,
            default => Category::Active,
        };
    }

    private function statusFor(int $i): MembershipStatus
    {
        return match ($i % 6) {
            2 => MembershipStatus::Trainee,
            4 => MembershipStatus::Inactive,
            default => MembershipStatus::Full,
        };
    }

    /** @return list<string> */
    private function firstNames(): array
    {
        return [
            'Ava', 'Liam', 'Priya', 'Chen', 'Sofia', 'Omar', 'Maya', 'Noah', 'Aisha', 'Diego',
            'Hannah', 'Kenji', 'Leila', 'Marcus', 'Nadia', 'Oliver', 'Fatima', 'Ravi', 'Elena', 'Jamal',
            'Grace', 'Sven', 'Yuki', 'Tomas', 'Amara', 'Felix', 'Ingrid', 'Hassan', 'Clara', 'Mateo',
        ];
    }

    /** @return list<string> */
    private function lastNames(): array
    {
        return [
            'Bennett', 'Okafor', 'Nguyen', 'Rossi', 'Khan', 'Andersson', 'Tremblay', 'Singh', 'Costa', 'Yamamoto',
            'Garcia', 'Murphy', 'Patel', 'Kowalski', 'Haddad', 'Schmidt', 'Lefebvre', 'Wong', 'Ferreira', 'Novak',
            'Reyes', 'Lindqvist', 'Abara', 'Park', 'Moreau', 'Dubois', 'Ivanova', 'Tan', 'Brar', 'Silva',
        ];
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
            'name' => 'DMV',
            'slug' => self::ROOT,
            'kind' => Kind::StandingCommittee,
            'description' => 'The DMV at large — the root of the org tree.',
            'children' => [
                // Governance & Operations — the org-level standing committees.
                $this->sc('Governance & Operations', [
                    $this->sc('Associated Friends'),
                    $this->sc('Awards'),
                    $this->sc("Chairs' Corner"),
                    $this->sc('Communications', capabilities: ['has_announcements' => true]),
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
     * `capabilities` overrides specific Kind-derived flags for the rare node whose
     * capability profile differs (e.g. Communications turning on announcements so
     * the news-editor Persona's `post-news` gate is satisfiable).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, bool>  $capabilities
     * @return array<string, mixed>
     */
    private function sc(string $name, array $children = [], array $capabilities = []): array
    {
        return ['name' => $name, 'kind' => Kind::StandingCommittee, 'children' => $children, 'capabilities' => $capabilities];
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
            'description' => $node['description'] ?? $this->aboutFor($node),
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

        // Per-node capability overrides win over the Kind-derived defaults.
        if (! empty($node['capabilities'])) {
            $attributes = array_merge($attributes, $node['capabilities']);
        }

        return $attributes;
    }

    /**
     * A deterministic fake "About Us" blurb (the Group's `description`, rendered
     * verbatim on the Group page). A Kind-aware opening line plus two sentences drawn
     * from rotating pools by a hash of the slug, so the ~80 demo Groups don't all read
     * the same yet reseed identically. Real content overrides it (`description` on the
     * node); this only fills the gap so every Group page has something to show.
     *
     * @param  array<string, mixed>  $node
     */
    private function aboutFor(array $node): string
    {
        $name = $node['name'];
        $kind = $node['kind'];

        $opener = match ($kind) {
            Kind::Program => "{$name} is one of the Department of Museum Volunteers' front-line programs at the Royal Ontario Museum.",
            Kind::StandingCommittee => "The {$name} committee is part of the Department of Museum Volunteers at the Royal Ontario Museum.",
            Kind::WorkingGroup => "{$name} is a working group within the DMV, supporting the day-to-day work of its program.",
            Kind::Project => "{$name} is a time-limited special project staffed by DMV volunteers.",
            Kind::Cohort => "{$name} was a trained docent cohort supporting a past ROM exhibition.",
        };

        $mission = [
            'Its volunteers bring the Museum\'s collections to life for visitors of all ages.',
            'Members meet regularly to plan activities, share training, and support one another.',
            'The group welcomes new volunteers who bring curiosity, warmth, and a love of learning.',
            'Together its members help the ROM connect people with art, culture, and nature.',
            'Volunteers here contribute their time, expertise, and enthusiasm throughout the season.',
            'The team works closely with Museum staff to deliver memorable visitor experiences.',
        ];

        $activity = [
            'Activities range from gallery tours to behind-the-scenes projects and community outreach.',
            'New members receive mentoring and hands-on training before taking on their roles.',
            'Reach out to the group\'s Chair to learn how to get involved.',
            'Meeting notes, schedules, and resources are shared with members through this page.',
            'The group takes pride in the ROM\'s mission and the community it serves.',
        ];

        $hash = crc32($node['slug'] ?? Str::slug($name));

        return implode(' ', [
            $opener,
            $mission[$hash % count($mission)],
            $activity[intdiv($hash, count($mission)) % count($activity)],
        ]);
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
        // demo member verified once, idempotently. The demo labels arrive as a
        // single "First Last" string; split on the first space into the two columns.
        $member = Member::firstOrCreate(['email' => $email], [
            'first_name' => Str::before($name, ' '),
            'last_name' => Str::after($name, ' '),
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
