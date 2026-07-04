<?php

namespace Database\Seeders;

use App\Enums\AccessTier;
use App\Enums\Category;
use App\Enums\GroupLogo;
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
    private const POOL_SIZE = 90;

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
     * A populated demo roster on top of the curated handful above: ~90 generated
     * volunteers spread across every Group by the affinity model in {@see distribute}
     * so each Group page shows a believable roster whose overlaps a DMV member would
     * recognise, plus the org-wide rule that the root DMV Group's roster is *everyone*
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
     * The nodes that are structural containers, not real Groups anyone belongs to:
     * the root and the three org-level sections ({@see tree()}). They get no roster
     * and no leadership — clicking one shows the sub-Groups it organises, not people.
     */
    private const STRUCTURAL_SLUGS = [self::ROOT, 'governance-operations', 'programs', 'special-projects'];

    /**
     * Populate every real Group with a believable roster, transcribed in shape (not
     * row-for-row) from the legacy per-program membership tables (dmv_docent,
     * dmv_walker, dmv_gi …). The old spread grabbed people by a blind coprime stride,
     * so rosters were noise; this reproduces the patterns a DMV member would
     * recognise, because they were measured from the live data:
     *
     * - Most volunteers are in one program, a quarter in two, tapering off — and the
     *   pairs that recur cluster (the visitor-facing trio; docents who also walk; the
     *   francophone pair). {@see assignProgramPortfolios}.
     * - A minority carry a Friends-of supporter membership on top {@see assignSupporterMemberships}.
     * - Working groups and cohorts draw from their parent program's roster — a
     *   sub-team volunteer is, in real life, also in the program {@see populateSubteams}.
     * - Committees and special projects staff from the pool {@see populateCommittees}, {@see populateProjects}.
     * - Finally every Group is floored to a small roster and given a Chair + Secretary
     *   so the Overview's "leadership at a glance" populates {@see ensureLeadership}.
     *
     * Committees are populated before their sub-teams so a Friends-of working group
     * draws from a parent that already has members. Every step is deterministic
     * (crc32 of the member email / Group slug — no fake()) and idempotent (memberships
     * key on the (Group, Member) pair, roles on the role), so re-seeding heals.
     *
     * @param  list<Member>  $pool
     */
    private function distribute(array $pool): void
    {
        $this->assignProgramPortfolios($pool);
        $this->guaranteeFrancophoneOverlap($pool);
        $this->assignSupporterMemberships($pool);
        $this->populateCommittees($pool);
        $this->populateSubteams($pool);
        $this->populateProjects($pool);
        $this->ensureLeadership($pool);
    }

    /**
     * Program relative sizes and co-membership clusters, transcribed from the legacy
     * active-membership counts: Gallery Interpreters is the largest, the visitor-
     * facing programs overlap heavily, docents also walk, and the two francophone
     * programs pair up. `weight` biases which program a volunteer calls home; adding
     * further programs prefers the home's cluster, so the overlaps land where they do
     * in real life rather than at random.
     *
     * @return array<string, array{weight: int, cluster: string}>
     */
    private function programAffinities(): array
    {
        return [
            'gallery-interpreters' => ['weight' => 9, 'cluster' => 'visitor'],
            'visitor-wayfinders' => ['weight' => 6, 'cluster' => 'visitor'],
            'visitor-guides' => ['weight' => 6, 'cluster' => 'visitor'],
            'docents' => ['weight' => 4, 'cluster' => 'docent'],
            'romforyou' => ['weight' => 3, 'cluster' => 'visitor'],
            'romwalks' => ['weight' => 3, 'cluster' => 'docent'],
            'reception' => ['weight' => 2, 'cluster' => 'visitor'],
            'dmv-hands-on-tours' => ['weight' => 2, 'cluster' => 'docent'],
            'guides-du-rom' => ['weight' => 2, 'cluster' => 'french'],
            'les-amis-francophiles' => ['weight' => 2, 'cluster' => 'french'],
            'rombus' => ['weight' => 2, 'cluster' => 'standalone'],
            'romtravel' => ['weight' => 2, 'cluster' => 'standalone'],
        ];
    }

    /**
     * Give each generated volunteer a realistic program portfolio: a weighted "home"
     * program, then a portfolio size drawn from the real overlap curve (≈57% one
     * program, 25% two, then a tapering tail), filled with programs that prefer the
     * home's affinity cluster. The docents↔ROMWalks overlap the maintainer called out
     * lives here and in {@see PersonaCatalogue} (Susan Wong), so it always shows.
     *
     * @param  list<Member>  $pool
     */
    private function assignProgramPortfolios(array $pool): void
    {
        $affinities = $this->programAffinities();
        $weights = array_map(fn (array $a) => $a['weight'], $affinities);

        foreach ($pool as $member) {
            $seed = $member->email;
            $home = $this->weightedPick($weights, $this->hash("home:{$seed}"));
            $size = $this->portfolioSizeFor($this->hash("size:{$seed}") % 100);

            $chosen = [$home => true];
            $candidates = $this->orderedCandidates($home, $affinities);

            for ($step = 0; count($chosen) < $size && $candidates !== []; $step++) {
                $idx = $this->hash("add:{$seed}:{$step}") % count($candidates);
                $chosen[$candidates[$idx]] = true;
                array_splice($candidates, $idx, 1);
            }

            foreach (array_keys($chosen) as $slug) {
                if (($group = $this->findGroup($slug)) !== null) {
                    $this->membership($group, $member, $this->membershipStatusFor("{$seed}:{$slug}"));
                }
            }
        }
    }

    /**
     * Guarantee the francophone signature overlap — volunteers in both Guides du ROM
     * and Les Amis Francophiles — the strongest small-program pair in the legacy data,
     * which the weighted draw can miss on so few members. Two deterministic pool
     * members are placed in both, mirroring the docents↔ROMWalks pair the weighting
     * already yields (and that Susan Wong carries among the Personas).
     *
     * @param  list<Member>  $pool
     */
    private function guaranteeFrancophoneOverlap(array $pool): void
    {
        $gdr = $this->findGroup('guides-du-rom');
        $famis = $this->findGroup('les-amis-francophiles');
        if ($gdr === null || $famis === null) {
            return;
        }

        $n = count($pool);
        $start = $this->hash('francophone') % $n;
        for ($k = 0; $k < 2; $k++) {
            $member = $pool[($start + $k * 11) % $n];
            $this->membership($gdr, $member, MembershipStatus::Full);
            $this->membership($famis, $member, MembershipStatus::Full);
        }
    }

    /**
     * Lay a Friends-of supporter membership on roughly a fifth of the pool, on top of
     * their program work — the layered "supporter" pattern the legacy data shows
     * (Friends-of members are overwhelmingly also active in a program).
     *
     * @param  list<Member>  $pool
     */
    private function assignSupporterMemberships(array $pool): void
    {
        $friends = [
            'bishop-white-fea',
            'friends-of-palaeontology-fop',
            'friends-of-earth-space-fes',
            'friends-of-global-south-asia-fsa',
            'friends-of-textiles-costume',
        ];

        foreach ($pool as $member) {
            if ($this->hash("friend:{$member->email}") % 100 >= 22) {
                continue;
            }

            $slug = $friends[$this->hash("friendpick:{$member->email}") % count($friends)];
            if (($group = $this->findGroup($slug)) !== null) {
                $this->membership($group, $member, MembershipStatus::Full);
            }
        }
    }

    /**
     * Staff every real standing committee (Governance & Operations children plus the
     * Friends-of committees — not the structural section containers) with a small
     * deterministic slice of the pool.
     *
     * @param  list<Member>  $pool
     */
    private function populateCommittees(array $pool): void
    {
        $committees = Group::where('kind', Kind::StandingCommittee)
            ->whereNotIn('slug', self::STRUCTURAL_SLUGS)
            ->orderBy('id')
            ->get();

        foreach ($committees as $group) {
            $size = 3 + ($this->hash("cmte:{$group->slug}") % 3);
            $this->drawInto($group, $pool, $size, "cmte:{$group->slug}");
        }
    }

    /**
     * Fill each working group and cohort from its parent's roster — a sub-team
     * volunteer belongs to the program (or committee) above it, so its members are
     * drawn from there, falling back to the pool only if the parent is somehow empty.
     *
     * @param  list<Member>  $pool
     */
    private function populateSubteams(array $pool): void
    {
        $subteams = Group::whereIn('kind', [Kind::WorkingGroup, Kind::Cohort])
            ->with('parent')
            ->orderBy('id')
            ->get();

        foreach ($subteams as $group) {
            $parentMembers = $group->parent ? $this->poolMembersIn($group->parent, $pool) : [];
            $source = $parentMembers !== [] ? $parentMembers : $pool;
            $size = 2 + ($this->hash("sub:{$group->slug}") % 3);
            $this->drawInto($group, $source, $size, "sub:{$group->slug}");
        }
    }

    /**
     * Staff each special project with a small slice of the pool.
     *
     * @param  list<Member>  $pool
     */
    private function populateProjects(array $pool): void
    {
        foreach (Group::where('kind', Kind::Project)->orderBy('id')->get() as $group) {
            $size = 2 + ($this->hash("proj:{$group->slug}") % 3);
            $this->drawInto($group, $pool, $size, "proj:{$group->slug}");
        }
    }

    /**
     * Floor every real Group to a small roster and give it a Chair and Secretary so
     * the Overview leadership list is never empty. Officers are only ever drawn from
     * the generated pool (Full standing), never the curated Personas, and a role is
     * assigned only when the Group has none yet — so a Persona-curated Chair (e.g.
     * Oliver Bennett on Docents) is left in place and never doubled. The root and its
     * section containers are skipped: the root's leadership is the executive, surfaced
     * separately, and the sections belong to no one.
     *
     * @param  list<Member>  $pool
     */
    private function ensureLeadership(array $pool): void
    {
        $groups = Group::whereNotIn('slug', self::STRUCTURAL_SLUGS)->orderBy('id')->get();

        foreach ($groups as $group) {
            $this->topUp($group, $pool, 3);

            $officers = $this->poolMembersIn($group, $pool, onlyFull: true);
            if (count($officers) < 2) {
                continue;
            }

            $this->ensureRole($group, $officers[0], Role::Chair);
            $this->ensureRole($group, $officers[1], Role::Secretary);
        }
    }

    /**
     * Add `size` deterministically-chosen members from `source` into the Group. A
     * prime stride from a slug-seeded start spreads the pick across the source so
     * rosters overlap without collapsing onto the same few people; membership()'s
     * firstOrCreate absorbs any repeat, keeping the draw idempotent.
     *
     * @param  list<Member>  $source
     */
    private function drawInto(Group $group, array $source, int $size, string $seed): void
    {
        $n = count($source);
        if ($n === 0) {
            return;
        }

        $start = $this->hash($seed) % $n;
        for ($k = 0, $size = min($size, $n); $k < $size; $k++) {
            $member = $source[($start + $k * 7) % $n];
            $this->membership($group, $member, $this->membershipStatusFor("{$seed}:{$member->email}"));
        }
    }

    /**
     * Ensure the Group has at least `min` living members, adding pool members not
     * already in it (deterministically, by a slug-seeded walk) until the floor is met.
     *
     * @param  list<Member>  $pool
     */
    private function topUp(Group $group, array $pool, int $min): void
    {
        $present = [];
        foreach ($this->poolMembersIn($group, $pool) as $member) {
            $present[$member->id] = true;
        }

        $count = count($present);
        $n = count($pool);
        $start = $this->hash("top:{$group->slug}") % $n;

        for ($k = 0; $k < $n && $count < $min; $k++) {
            $member = $pool[($start + $k) % $n];
            if (isset($present[$member->id])) {
                continue;
            }

            $this->membership($group, $member, MembershipStatus::Full);
            $present[$member->id] = true;
            $count++;
        }
    }

    /**
     * The pool members currently in the Group (living — Resigned/Deceased excluded),
     * in pool order so downstream picks are deterministic. `onlyFull` narrows to Full
     * standing, the set eligible to hold an officer role.
     *
     * @param  list<Member>  $pool
     * @return list<Member>
     */
    private function poolMembersIn(Group $group, array $pool, bool $onlyFull = false): array
    {
        $query = GroupMember::where('group_id', $group->id);

        if ($onlyFull) {
            $query->where('status', MembershipStatus::Full);
        } else {
            $query->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased]);
        }

        $ids = array_flip($query->pluck('member_id')->all());

        return array_values(array_filter($pool, fn (Member $member) => isset($ids[$member->id])));
    }

    /** Attach the role to the member's membership only if no one in the Group holds it. */
    private function ensureRole(Group $group, Member $member, Role $role): void
    {
        $held = GroupMember::where('group_id', $group->id)
            ->whereHas('roles', fn ($query) => $query->where('role', $role))
            ->exists();

        if ($held) {
            return;
        }

        GroupMember::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->first()
            ?->roles()->firstOrCreate(['role' => $role]);
    }

    /** A Group by slug, or null — lets callers skip a node absent from the tree. */
    private function findGroup(string $slug): ?Group
    {
        return Group::where('slug', $slug)->first();
    }

    /** crc32 of the key — the deterministic, fake()-free source of every pseudo-random pick. */
    private function hash(string $key): int
    {
        return crc32($key);
    }

    /**
     * Pick a slug from a weighted map by walking the cumulative weights to the point
     * the hash lands on.
     *
     * @param  array<string, int>  $weights
     */
    private function weightedPick(array $weights, int $hash): string
    {
        $point = $hash % array_sum($weights);

        foreach ($weights as $slug => $weight) {
            if ($point < $weight) {
                return $slug;
            }
            $point -= $weight;
        }

        return array_key_first($weights);
    }

    /**
     * How many programs a volunteer belongs to, from a 0–99 roll shaped to the real
     * overlap curve: most in one, a quarter in two, a tapering tail up to five.
     */
    private function portfolioSizeFor(int $roll): int
    {
        return match (true) {
            $roll < 57 => 1,
            $roll < 82 => 2,
            $roll < 92 => 3,
            $roll < 96 => 4,
            default => 5,
        };
    }

    /**
     * The programs to consider adding after the home program, ordered by affinity:
     * same cluster first, then the cluster that most overlaps it in the legacy data
     * (docents and the visitor programs cross-pollinate), then the rest.
     *
     * @param  array<string, array{weight: int, cluster: string}>  $affinities
     * @return list<string>
     */
    private function orderedCandidates(string $home, array $affinities): array
    {
        $cluster = $affinities[$home]['cluster'];
        $secondary = match ($cluster) {
            'docent' => 'visitor',
            default => 'docent',
        };

        $same = $sec = $rest = [];
        foreach ($affinities as $slug => $affinity) {
            if ($slug === $home) {
                continue;
            }
            match (true) {
                $affinity['cluster'] === $cluster => $same[] = $slug,
                $affinity['cluster'] === $secondary => $sec[] = $slug,
                default => $rest[] = $slug,
            };
        }

        return array_merge($same, $sec, $rest);
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

    /**
     * A within-Group standing for a generated membership: mostly Full, with a modest
     * spread of Trainee / Inactive / Emeritus so rosters aren't uniform. Seeded on the
     * (member, Group) key so a member's standing in a Group is stable across reseeds.
     * (Full LOA windows are a Persona concern — {@see PersonaCatalogue} — not the pool.)
     */
    private function membershipStatusFor(string $key): MembershipStatus
    {
        return match ($this->hash($key) % 12) {
            3 => MembershipStatus::Trainee,
            7 => MembershipStatus::Inactive,
            10 => MembershipStatus::Emeritus,
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
                    ], GroupLogo::Docents),
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
     * A program node. Pass a logo to give it its own identity mark on the
     * launcher (PRD #253); most programs stay null and show the generic fallback.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function program(string $name, array $children = [], ?GroupLogo $logo = null): array
    {
        return ['name' => $name, 'kind' => Kind::Program, 'children' => $children, 'logo' => $logo];
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
            // Identity mark on the launcher (PRD #253); null → generic fallback,
            // which is the common case across the demo tree.
            'logo_key' => $node['logo'] ?? null,
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
