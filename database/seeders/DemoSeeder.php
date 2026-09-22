<?php

namespace Database\Seeders;

use App\Enums\AccessTier;
use App\Enums\Category;
use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MeetingLinkKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Enums\Scope;
use App\Enums\ShiftAudience;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\HoursAdjustment;
use App\Models\HoursRecord;
use App\Models\Meeting;
use App\Models\MeetingLink;
use App\Models\Member;
use App\Models\News;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Personas\Persona;
use App\Personas\PersonaCatalogue;
use App\Support\OrgTime;
use App\Support\ProfilePhotoStorage;
use Carbon\CarbonImmutable;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
 * - The source groups its top level into four sections: Governance &
 *   Operations, Programs, Special Projects, and Friends. Three are page-less
 *   container sections (Governance & Operations, Programs, Friends), modelled
 *   as {@see Kind::Container} (PRD #289). Special Projects is a real
 *   coordinating Group, modelled as {@see Kind::StandingCommittee}.
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
     * The base for the synthetic `meeting_id` on a seeded meeting-hours row. High enough
     * that it can never collide with a real `meetings` id, and non-zero so the row reads
     * as a meeting row rather than the no-meeting sentinel
     * ({@see HoursRecord::NO_MEETING}). Nothing joins to it — see {@see meetingHours()}.
     */
    private const MEETING_ID_BASE = 900000;

    /**
     * How many of a Group's living roster record hours. A handful, so the fiscal-year
     * matrix prints on a page rather than running to twenty rows a Group.
     */
    private const HOURS_RECORDERS = 6;

    /**
     * Rows an Hours insert statement. Large enough that the whole seed is a handful of
     * statements, small enough to stay well inside the placeholder limit.
     */
    private const HOURS_CHUNK = 500;

    /** The Docents' daily tour labels and the booked one, named as the Docents program names them. */
    private const HIGHLIGHTS_TOUR = 'Museum Highlights';

    private const GALLERY_TOUR = 'Gallery/Theme';

    private const GROUP_TOUR = 'Group Tour';

    /**
     * The Docents' daily roster, start hour => tour label: five one-hour tours from 11:00,
     * one Docent each, every day of the week, the label alternating by hour. This is the
     * legacy weekly template every Docents month is generated from.
     *
     * @var array<int, string>
     */
    private const TOUR_HOURS = [
        11 => self::HIGHLIGHTS_TOUR,
        12 => self::GALLERY_TOUR,
        13 => self::HIGHLIGHTS_TOUR,
        14 => self::GALLERY_TOUR,
        15 => self::HIGHLIGHTS_TOUR,
    ];

    /**
     * A month's Group Tours, as day-offsets from the first (all ≤ 24, inside even February).
     * Shaped on a year of legacy bookings: a free late-morning tour with one Docent, paid
     * evening tours with two, and school groups at 10:00 with three or four. None starts on
     * a daily tour's hour, so the (Schedule, start) key stays unique. The booking itself
     * (client name, group size) is deferred (ADR-0021), so each is only its staffing.
     *
     * @var list<array{day: int, start: array{int, int}, minutes: int, capacity: int}>
     */
    private const GROUP_TOURS = [
        ['day' => 3, 'start' => [10, 0], 'minutes' => 60, 'capacity' => 3],
        ['day' => 8, 'start' => [18, 0], 'minutes' => 60, 'capacity' => 2],
        ['day' => 14, 'start' => [11, 30], 'minutes' => 45, 'capacity' => 1],
        ['day' => 19, 'start' => [18, 0], 'minutes' => 60, 'capacity' => 2],
        ['day' => 24, 'start' => [10, 0], 'minutes' => 75, 'capacity' => 4],
    ];

    public const VISITOR_GUIDES = 'visitor-guides';

    /** The Visitor Guides' two shift types, named as legacy names them. Desk is the watched one. */
    private const DESK = 'Desk';

    private const SHADOW = 'Shadow';

    /**
     * The Visitor Guides' daily desk roster, read off the legacy weekly template and a summer of
     * legacy months: a one-hour Desk shift on every hour from 10:00 to 15:00 with two guides,
     * and a one-seat Shadow shift beside it for a trainee.
     *
     * @var array<int, array{kind: string, capacity: int}>
     */
    private const DESK_ROSTER = [
        ['kind' => self::DESK, 'capacity' => 2],
        ['kind' => self::SHADOW, 'capacity' => 1],
    ];

    /** @var list<int> */
    private const DESK_HOURS = [10, 11, 12, 13, 14, 15];

    /** Months the Visitor Guides also staff Mondays (legacy "2 Week Pattern Summer"). */
    private const DESK_SUMMER_MONTHS = [7, 8];

    public const GUIDES_DU_ROM = 'guides-du-rom';

    /** The Guides du ROM daily tour and the monthly group tour, named as legacy names them. */
    private const GUIDES_CHOICE_TOUR = 'Le choix du guide';

    private const GDR_GROUP_TOUR = 'Visite de groupe';

    /**
     * The monthly free group tour, read off a year of legacy bookings: one guide, 11:30 to 12:15,
     * on a Saturday late in the month (the fourth Saturday here).
     */
    private const GDR_GROUP_TOUR_SATURDAY = 4;

    /**
     * The Reception roster, read off the legacy weekly template: three-hour shifts, one volunteer
     * each, from 09:30 and 12:30 Tuesday to Friday, and the morning alone on the weekend. No
     * shift on Monday. Keyed by ISO weekday, 1 (Monday) to 7 (Sunday).
     *
     * @var array<int, list<array{int, int}>>
     */
    private const RECEPTION_ROSTER = [
        2 => [[9, 30], [12, 30]],
        3 => [[9, 30], [12, 30]],
        4 => [[9, 30], [12, 30]],
        5 => [[9, 30], [12, 30]],
        6 => [[9, 30]],
        7 => [[9, 30]],
    ];

    /**
     * The Reception shifts a regular volunteer holds every week, as `ISO weekday-hour`. Legacy
     * fills these near always and leaves the rest mostly open, which puts a month near half full.
     *
     * @var list<string>
     */
    private const RECEPTION_REGULARS = ['2-12', '3-9', '4-9', '5-9', '5-12'];

    /** Tours that ended within this many days stay unrecorded: the sign-outs still to come. */
    private const UNRECORDED_DAYS = 2;

    /** The name of the recent, all-past Schedule each collecting Group gets its sign-out seats on. */
    public const RECENT_SCHEDULE_NAME = 'Recent shifts';

    /** How far back the recent Schedule opens — comfortably past the last of its Shifts. */
    private const RECENT_SPAN_DAYS = 30;

    /** Seats on each recent Shift (also its capacity, so the roster reads as fully worked). */
    private const RECENT_SEATS = 3;

    /**
     * Day-offsets before now for the recent Shifts — all ended, all inside the outstanding window
     * ({@see SignUp::OUTSTANDING_WINDOW_DAYS}), so the counts land in the current fiscal year and
     * the first Shift's null seats show up on the outstanding-shifts panel.
     */
    private const RECENT_SHIFT_OFFSETS = [3, 10, 17, 24];

    /**
     * The Groups whose only route into Summary Visitor Interactions is hand-typed
     * `extra_interactions` on the Hours tab (ADR-0023 §6): the ones with no per-shift visitor
     * data at all. ROM Travel runs no scheduling, Hands-on Tours and ROMBus have no visitor
     * table, two Friends committees never staffed a desk, and the DMV root carries only its own
     * roll-up total. Every one of them needs a row here or it drops out of the report entirely.
     *
     * @var list<string>
     */
    private const EXTRA_INTERACTION_GROUPS = [
        self::ROOT,
        'romtravel',
        'dmv-hands-on-tours',
        'rombus',
        'friends-of-palaeontology-fop',
        'friends-of-global-south-asia-fsa',
    ];

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
        $this->scheduling();
        $this->afterShiftRecords();
        $this->hours();
        $this->news();
        $this->meetings();
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

        // The org-wide mail franchise (ADR-0024 §5): the Executive, Records, and
        // Awards Groups may send the org-wide Broadcast Audiences. A member of any of
        // them is an org-wide sender. Org structure, not a Persona; skips a Group
        // absent from a trimmed tree rather than failing.
        foreach ([Group::EXECUTIVE_SLUG, self::RECORDS, 'awards'] as $slug) {
            if (($group = $this->findGroup($slug)) !== null) {
                $this->steward($group, StewardshipFunction::OrgMail);
            }
        }
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
     * The slugs that get no roster and no leadership: the org root (its own bespoke
     * handling — root enrolment is everyone, surfaced separately) and the
     * {@see Kind::Container} section peers, which are pure scaffolding nobody belongs
     * to — clicking one shows the sub-Groups it organises, not people. Derived from
     * `Kind::Container` (PRD #289), not a hand-listed slug set, so a new container is
     * skipped automatically. `special-projects`, now a real Group, is deliberately
     * absent — it gets a roster and leadership like any coordinating committee.
     *
     * @return list<string>
     */
    private function structuralSlugs(): array
    {
        return array_merge(
            [self::ROOT],
            Group::where('kind', Kind::Container)->pluck('slug')->all(),
        );
    }

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
            ->whereNotIn('slug', $this->structuralSlugs())
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
        $groups = Group::whereNotIn('slug', $this->structuralSlugs())->orderBy('id')->get();

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
     * Scheduling goes live (#364, PRD #352, ADR-0021), shaped like the real Docents roster.
     * Seeded last, after every Member and membership exists, so Sign-ups can seat real
     * Docents volunteers.
     *
     * Docents is the intended demo Group: the only demo Group carrying a Scheduler Persona
     * (James Tremblay), so the drop-a-Shift cancellation email (#373) has a real recipient,
     * and a kinded Group. It gets two published month Schedules, named for their month as
     * legacy names them: last month, worked and signed out, and this month, part-way through.
     * Next month follows as an empty draft.
     *
     * Each month is the roster Docents actually run, read off the legacy weekly template:
     * five one-hour tours every day from 11:00 to 15:00, one Docent each, the label
     * alternating by hour ({@see TOUR_HOURS}). A few Group Tours sit on top
     * ({@see GROUP_TOURS}), the only Shifts with more than one seat.
     *
     * Faker-free and idempotent: Schedules and kinds key on (Group, name), Shifts on
     * (Schedule, start), and Sign-ups on the (Shift, Member) pair.
     */
    private function scheduling(): void
    {
        $docents = Group::where('slug', self::PROGRAM)->firstOrFail();

        $month = CarbonImmutable::instance(now())->startOfMonth();
        $lastMonth = $month->subMonth();

        $kinds = $this->shiftKinds($docents);

        $previous = $this->monthSchedule($docents, $lastMonth, 'Last month\'s docent tour roster, worked and signed out.');
        $current = $this->monthSchedule($docents, $month, 'The current-month docent tour roster — sign up for a tour below.');

        $this->tourShifts($previous, $lastMonth, $kinds);
        $this->tourShifts($current, $month, $kinds);

        $this->seatTours($docents, $previous, $current);

        $this->draftNextMonth($docents, $month);

        $this->visitorGuidesScheduling($lastMonth, $month);

        $this->guidesDuRomScheduling($lastMonth, $month);

        $this->receptionScheduling($lastMonth, $month);
    }

    /**
     * The Visitor Guides desk roster, on the same two published months as Docents. Each open day
     * has a Desk and a Shadow shift on every hour from 10:00 to 15:00 ({@see DESK_ROSTER}). The
     * desk closes on Mondays except in summer, as legacy runs it.
     *
     * Desk is the one watched kind (#487, ADR-0024 §7): the Group runs the empty-desk alert, and
     * an upcoming Desk shift nobody has taken is what it reports. Skipped silently if the Group
     * is absent.
     */
    private function visitorGuidesScheduling(CarbonImmutable $lastMonth, CarbonImmutable $month): void
    {
        $group = Group::where('slug', self::VISITOR_GUIDES)->first();

        if ($group === null) {
            return;
        }

        $kinds = [
            self::DESK => ShiftKind::firstOrCreate(
                ['group_id' => $group->id, 'name' => self::DESK],
                ['active' => true, 'alert_when_empty' => true, 'sort_order' => 0],
            ),
            self::SHADOW => ShiftKind::firstOrCreate(
                ['group_id' => $group->id, 'name' => self::SHADOW],
                ['active' => true, 'sort_order' => 1],
            ),
        ];

        $previous = $this->monthSchedule($group, $lastMonth, 'Last month\'s Visitor Guides desk roster, worked and signed out.');
        $current = $this->monthSchedule($group, $month, 'The current-month desk roster. Sign up for a desk hour below.');

        foreach ([[$previous, $lastMonth], [$current, $month]] as [$schedule, $start]) {
            $specs = [];
            for ($day = 0; $day < $start->daysInMonth; $day++) {
                $date = $start->addDays($day);
                if ($date->isMonday() && ! in_array($date->month, self::DESK_SUMMER_MONTHS, true)) {
                    continue;
                }
                foreach (self::DESK_HOURS as $hour) {
                    foreach (self::DESK_ROSTER as $slot) {
                        $specs[] = ['day' => $day, 'start' => [$hour, 0], 'minutes' => 60, ...$slot];
                    }
                }
            }
            $this->writeShifts($schedule, $start, $specs, $kinds);
        }

        $this->seatRoster(
            $group,
            [$previous, $current],
            fn (Shift $shift) => $this->deskSeats($shift),
            fn (Shift $shift) => [10, 70],
        );
    }

    /**
     * How many seats on a Visitor Guides shift are taken, drawn per seat. Legacy summer months
     * filled about 75% of Desk seats: Saturday most, Sunday least, 15:00 last. Shadow seats
     * almost never fill. The same odds hold before and after the shift, since legacy desk
     * hours are not always worked.
     */
    private function deskSeats(Shift $shift): int
    {
        $wallClock = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'));

        $chance = match (true) {
            $shift->kind?->name === self::SHADOW => 5,
            $wallClock->isSaturday() => 80,
            $wallClock->isSunday() => 55,
            $wallClock->isMonday() => 65,
            default => 75,
        };
        if ($wallClock->hour >= 15) {
            $chance -= 25;
        }

        $taken = 0;
        for ($seat = 0; $seat < $shift->capacity; $seat++) {
            if ($this->spread($shift->starts_at->timestamp + $seat * 7919, 0, 99) < $chance) {
                $taken++;
            }
        }

        return $taken;
    }

    /**
     * The Guides du ROM tour roster, on the same two published months as Docents. Legacy runs one
     * French tour a day at 14:00, one guide, every day but Monday, labelled "Le choix du guide".
     * One free group tour a month sits on top, on a Saturday at 11:30 ({@see GDR_GROUP_TOUR_SATURDAY}).
     *
     * Ended tours are full. About nine upcoming tours in ten are already taken, as in the legacy
     * months open for sign-up. Counts are small, often zero, and split into five origins.
     * Skipped silently if the Group is absent.
     */
    private function guidesDuRomScheduling(CarbonImmutable $lastMonth, CarbonImmutable $month): void
    {
        $group = Group::where('slug', self::GUIDES_DU_ROM)->first();

        if ($group === null) {
            return;
        }

        $kinds = [];
        foreach ([self::GUIDES_CHOICE_TOUR, self::GDR_GROUP_TOUR] as $order => $name) {
            $kinds[$name] = ShiftKind::firstOrCreate(
                ['group_id' => $group->id, 'name' => $name],
                ['active' => true, 'sort_order' => $order],
            );
        }

        $previous = $this->monthSchedule($group, $lastMonth, 'Le calendrier des visites du mois dernier, données et signées.');
        $current = $this->monthSchedule($group, $month, 'Les visites du mois. Inscrivez-vous à une visite ci-dessous.');

        foreach ([[$previous, $lastMonth], [$current, $month]] as [$schedule, $start]) {
            $specs = [];
            for ($day = 0; $day < $start->daysInMonth; $day++) {
                if (! $start->addDays($day)->isMonday()) {
                    $specs[] = ['day' => $day, 'start' => [14, 0], 'minutes' => 60, 'capacity' => 1, 'kind' => self::GUIDES_CHOICE_TOUR];
                }
            }
            $saturday = $start->nthOfMonth(self::GDR_GROUP_TOUR_SATURDAY, CarbonImmutable::SATURDAY);
            $specs[] = ['day' => $saturday->day - 1, 'start' => [11, 30], 'minutes' => 45, 'capacity' => 1, 'kind' => self::GDR_GROUP_TOUR];

            $this->writeShifts($schedule, $start, $specs, $kinds);
        }

        $this->seatRoster(
            $group,
            [$previous, $current],
            fn (Shift $shift) => $shift->ends_at->isPast() || $this->spread($shift->starts_at->timestamp, 0, 99) < 90 ? 1 : 0,
            fn (Shift $shift) => $shift->kind?->name === self::GDR_GROUP_TOUR ? [15, 25] : [0, 6],
        );
    }

    /**
     * The Reception roster, on the same two published months as Docents ({@see RECEPTION_ROSTER}).
     * The shifts carry no kind, as legacy Reception has no shift labels. A regular holds the same
     * few shifts each week ({@see RECEPTION_REGULARS}); the others are mostly open, before and
     * after the day alike. Reception collects no visitor count, so no seat carries one.
     * Skipped silently if the Group is absent.
     */
    private function receptionScheduling(CarbonImmutable $lastMonth, CarbonImmutable $month): void
    {
        $group = Group::where('slug', self::RECEPTION)->first();

        if ($group === null) {
            return;
        }

        $previous = $this->monthSchedule($group, $lastMonth, 'Last month\'s Reception roster.');
        $current = $this->monthSchedule($group, $month, 'The current-month Reception roster. Sign up for a shift below.');

        foreach ([[$previous, $lastMonth], [$current, $month]] as [$schedule, $start]) {
            $specs = [];
            for ($day = 0; $day < $start->daysInMonth; $day++) {
                foreach (self::RECEPTION_ROSTER[$start->addDays($day)->dayOfWeekIso] ?? [] as $time) {
                    $specs[] = ['day' => $day, 'start' => $time, 'minutes' => 180, 'capacity' => 1, 'kind' => null];
                }
            }
            $this->writeShifts($schedule, $start, $specs, []);
        }

        $this->seatRoster(
            $group,
            [$previous, $current],
            function (Shift $shift) {
                $wallClock = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'));
                $regular = in_array($wallClock->dayOfWeekIso.'-'.$wallClock->hour, self::RECEPTION_REGULARS, true);

                return $this->spread($shift->starts_at->timestamp, 0, 99) < ($regular ? 90 : 10) ? 1 : 0;
            },
        );
    }

    /**
     * A published month Schedule on the Group, keyed on (Group, name) so a reseed heals
     * rather than duplicates. Last month's seed drafted this month ahead
     * ({@see draftNextMonth()}), so a reseed without a fresh migrate finds that draft here;
     * publish it rather than leave it hidden.
     */
    private function monthSchedule(Group $group, CarbonImmutable $month, string $description): Schedule
    {
        $schedule = Schedule::firstOrCreate(
            ['group_id' => $group->id, 'name' => $month->format('F Y')],
            [
                'starts_on' => $month->toDateString(),
                'ends_on' => $month->endOfMonth()->toDateString(),
                'state' => ScheduleState::Published,
                'description' => $description,
            ],
        );

        if ($schedule->state !== ScheduleState::Published) {
            $schedule->update(['state' => ScheduleState::Published]);
        }

        return $schedule;
    }

    /**
     * Write a month of tours onto the Schedule: the daily roster on every day of the month,
     * then the month's Group Tours.
     *
     * @param  array<string, ShiftKind>  $kinds
     */
    private function tourShifts(Schedule $schedule, CarbonImmutable $month, array $kinds): void
    {
        $specs = [];
        for ($day = 0; $day < $month->daysInMonth; $day++) {
            foreach (self::TOUR_HOURS as $hour => $kind) {
                $specs[] = ['day' => $day, 'start' => [$hour, 0], 'minutes' => 60, 'capacity' => 1, 'kind' => $kind];
            }
        }
        foreach (self::GROUP_TOURS as $tour) {
            $specs[] = [...$tour, 'kind' => self::GROUP_TOUR];
        }

        $this->writeShifts($schedule, $month, $specs, $kinds);
    }

    /**
     * Write Shifts onto the Schedule, one per spec, each a day-offset from `$month`. Instants
     * are built on the org wall clock and stored in UTC ({@see OrgTime}), the same path the
     * authoring form takes, so an 11:00 Shift reads as 11:00 for every viewer.
     *
     * A month is a few hundred Shifts, so they go in one bulk insert rather than a
     * `firstOrCreate` each (#431: the test suite seeds this class dozens of times). Only
     * (start, kind) pairs not already on the Schedule are inserted, so a reseed heals rather
     * than duplicates.
     *
     * @param  list<array{day: int, start: array{int, int}, minutes: int, capacity: int, kind: ?string}>  $specs
     * @param  array<string, ShiftKind>  $kinds
     */
    private function writeShifts(Schedule $schedule, CarbonImmutable $month, array $specs, array $kinds): void
    {
        $existing = $schedule->shifts()->get(['starts_at', 'shift_kind_id'])
            ->mapWithKeys(fn (Shift $shift) => [$shift->starts_at->toDateTimeString().'|'.$shift->shift_kind_id => true]);

        $now = now();
        $rows = [];
        foreach ($specs as $spec) {
            $start = $month->addDays($spec['day'])->setTime(...$spec['start']);
            $startsAt = OrgTime::toUtc($start->toDateTimeString());
            $kindId = $spec['kind'] === null ? null : $kinds[$spec['kind']]->id;

            if ($existing->has($startsAt.'|'.$kindId)) {
                continue;
            }

            $rows[] = [
                'schedule_id' => $schedule->id,
                'starts_at' => $startsAt,
                'ends_at' => OrgTime::toUtc($start->addMinutes($spec['minutes'])->toDateTimeString()),
                'capacity' => $spec['capacity'],
                'shift_kind_id' => $kindId,
                'audience' => ShiftAudience::Group->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::HOURS_CHUNK) as $chunk) {
            Shift::insert($chunk);
        }
    }

    /**
     * An empty next-month draft Schedule on Docents (#530): the state a Scheduler is in right
     * after creating one, so the Create a Schedule walkthrough shoots its Draft badge, Publish,
     * and New shift controls. A draft is hidden from Members, so the member walkthroughs read
     * as before. Keyed on (Group, name) so a reseed heals rather than duplicates.
     */
    private function draftNextMonth(Group $docents, CarbonImmutable $month): void
    {
        $next = $month->addMonth();

        Schedule::firstOrCreate(
            ['group_id' => $docents->id, 'name' => $next->format('F Y')],
            [
                'starts_on' => $next->toDateString(),
                'ends_on' => $next->endOfMonth()->toDateString(),
                'state' => ScheduleState::Draft,
                'description' => 'Next month\'s docent tour roster, still being drafted.',
            ],
        );
    }

    /**
     * Seat the Docents roster on its tours the way a real month fills. Every ended tour
     * was worked, so it is full. An upcoming tour is taken or not by a deterministic draw
     * weighted the way Docents sign up ({@see tourFillChance()}). An upcoming Group Tour is
     * left one seat short, so a walkthrough always has a multi-seat Shift with room.
     *
     * Counts sit near the legacy averages: about 16 on a Museum Highlights tour (never
     * empty), about 12 on a Gallery/Theme tour (sometimes a recorded zero), and a share of
     * a group on a Group Tour.
     */
    private function seatTours(Group $group, Schedule $previous, Schedule $current): void
    {
        $this->seatRoster(
            $group,
            [$previous, $current],
            fn (Shift $shift) => match (true) {
                $shift->ends_at->isPast() => $shift->capacity,
                $shift->capacity > 1 => $shift->capacity - 1,
                default => $this->spread($shift->starts_at->timestamp, 0, 99) < $this->tourFillChance($shift) ? 1 : 0,
            },
            fn (Shift $shift) => match ($shift->kind?->name) {
                self::HIGHLIGHTS_TOUR => [5, 28],
                self::GALLERY_TOUR => [0, 24],
                default => [10, 25],
            },
        );
    }

    /**
     * Seat a Group's roster on its month Schedules. `$seatsFor` says how many seats on a Shift
     * are taken, and `$countRange` the visitor count range a worked seat carries, where the
     * Group collects one.
     *
     * Worked Shifts carry the numbers filed at sign-out ({@see seatRow()}), except Shifts
     * ended in the last {@see UNRECORDED_DAYS} days. Those seats stay null, the sign-outs
     * still to come, which is what the outstanding-shifts panel reads (ADR-0023 §5).
     *
     * The Member Persona is placed by hand, not by the draw (#529): on the latest ended
     * Shift, still owed a number, and on the month's last Shift, a seat she can drop. The draw
     * skips her, so her My sign-ups panel shows exactly those two. This applies only on a
     * Group she belongs to.
     *
     * Seats are drawn from the Group's living roster (never a departed Member), so every
     * seat is a legitimate group-audience Sign-up. Written in one bulk upsert on the
     * (Shift, Member) grain, like {@see writeSeatRecords()}.
     *
     * @param  list<Schedule>  $schedules  oldest first; the last is the current month
     * @param  callable(Shift): int  $seatsFor
     * @param  (callable(Shift): array{int, int})|null  $countRange  null for a Group that collects no count
     */
    private function seatRoster(Group $group, array $schedules, callable $seatsFor, ?callable $countRange = null): void
    {
        $current = end($schedules);
        $roster = $this->livingRoster($group);
        $persona = collect($roster)->first($this->isMemberPersona(...));
        $pool = array_values(array_filter($roster, fn (Member $member) => ! $this->isMemberPersona($member)));

        if ($pool === []) {
            return;
        }

        $now = CarbonImmutable::now();
        $unrecordedFrom = $now->subDays(self::UNRECORDED_DAYS);

        $shifts = Shift::whereIn('schedule_id', array_map(fn (Schedule $schedule) => $schedule->id, $schedules))
            ->with('kind')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $personaShifts = [];
        if ($persona !== null) {
            $lastEnded = $shifts->filter(fn (Shift $shift) => $shift->ends_at->lessThan($now))->last();
            $lastTour = $shifts->where('schedule_id', $current->id)->last();

            foreach ([$lastEnded, $lastTour] as $shift) {
                if ($shift !== null) {
                    $personaShifts[$shift->id] = true;
                }
            }
        }

        $rows = [];
        $cursor = 0;
        foreach ($shifts as $shift) {
            $ended = $shift->ends_at->lessThan($now);
            $seats = $seatsFor($shift);

            $members = [];
            if (isset($personaShifts[$shift->id])) {
                $members[] = $persona;
                $seats = max($seats, 1);
            }
            while (count($members) < $seats) {
                $members[] = $pool[$cursor++ % count($pool)];
            }

            $recorded = $group->collects_visitor_count && $ended && $shift->ends_at->lessThan($unrecordedFrom);
            foreach ($members as $seat => $member) {
                $rows[] = $this->seatRow($group, $shift, $member, $seat, $recorded ? $countRange($shift) : null, $now);
            }
        }

        $this->writeSeatRecords($rows);
    }

    /**
     * The chance, in percent, that an upcoming daily tour is already taken. Legacy's month
     * in sign-up sat near 56% filled: weekday tours from 11:00 to 14:00 go first, 15:00
     * and weekend tours last. These weights land the month near 58%.
     */
    private function tourFillChance(Shift $shift): int
    {
        $wallClock = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'));
        $late = $wallClock->hour >= 15;

        return match (true) {
            $wallClock->isWeekend() && $late => 20,
            $wallClock->isWeekend(), $late => 45,
            default => 70,
        };
    }

    /**
     * One seat as an upsert row, in the column set {@see writeSeatRecords()} writes. An
     * unrecorded seat (a null `$range`) is null on every visitor column, the outstanding
     * marker. A recorded one carries a count drawn from `$range`. Extra interactions and the
     * five origins (ADR-0023 §3) follow the Group's own switches. Deterministic ({@see spread()}), so a local reseed and a staging
     * deploy read the same.
     *
     * @param  array{int, int}|null  $range
     * @return array<string, int|CarbonImmutable|null>
     */
    private function seatRow(Group $group, Shift $shift, Member $member, int $seat, ?array $range, CarbonImmutable $now): array
    {
        $count = null;
        $extra = null;
        $origins = array_fill(0, 5, null);

        if ($range !== null) {
            $seed = $shift->starts_at->timestamp * 7 + $seat;
            [$min, $max] = $range;

            $count = $this->spread($seed, $min, $max);
            $extra = $group->collects_extra_interactions ? $this->spread($seed + 1, 0, 12) : null;
            $origins = $group->collects_visitor_provenance ? $this->splitProvenance($count, $seed + 2) : $origins;
        }

        return [
            'shift_id' => $shift->id,
            'member_id' => $member->id,
            'visitor_count' => $count,
            'extra_interaction_count' => $extra,
            'visitors_france_europe' => $origins[0],
            'visitors_quebec' => $origins[1],
            'visitors_toronto' => $origins[2],
            'visitors_rest_of_canada' => $origins[3],
            'visitors_other_countries' => $origins[4],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** The Member Persona ({@see PersonaCatalogue::MEMBER_EMAIL}) — the seed's plain Docents Member. */
    private function isMemberPersona(Member $member): bool
    {
        return $member->email === PersonaCatalogue::MEMBER_EMAIL;
    }

    /**
     * The after-the-shift visitor record goes live in the demo org (#474, PRD #443, ADR-0023 §2-§6).
     * The whole feature shipped correct and invisible on staging because nothing turned the Group
     * switches on or wrote a single count — the same failure as #428. This seeds the Sign-up half:
     * recent, ended Shifts on every Group that collects a visitor count, their seats carrying the
     * numbers a volunteer files at sign-out. The Hours-tab half — `extra_interactions` — rides
     * {@see hours()}.
     *
     * The switches themselves are set per Group in the curated tree (the capability overrides on
     * the program nodes, exactly as ROMBus turns scheduling off), so this reads them back rather
     * than restating the mapping: a Group collects a count, and on top of it the tour-leading split
     * ({@see seatRecord()}), precisely as its own flags say.
     *
     * Each collecting Group but Docents, Visitor Guides and GDR gets one recent Schedule of ended
     * Shifts. The first is left unrecorded on purpose — null on every seat — so the outstanding-shifts panel has something to show the
     * personas seated there (the roster is id-ordered and personas, seeded first, sort ahead of the
     * generated pool); the rest carry counts, a few of them a deliberate recorded zero, never
     * confused with the null.
     *
     * Faker-free and idempotent: the Schedule keys on (Group, name), Shifts on (Schedule, start),
     * and every seat value is written in one bulk upsert on the (Shift, Member) grain — so a reseed
     * restates each seat to its absolute value rather than doubling, the same reason {@see hours()}
     * upserts rather than looping a row at a time (#431).
     */
    private function afterShiftRecords(): void
    {
        // Docents, Visitor Guides and GDR record on their own month rosters instead
        // ({@see seatRoster()}): a separate Schedule of three-hour Shifts is not a shape they work.
        $groups = Group::where('collects_visitor_count', true)
            ->whereNotIn('slug', [self::PROGRAM, self::VISITOR_GUIDES, self::GUIDES_DU_ROM])
            ->orderBy('id')
            ->get();

        $now = OrgTime::now();

        $rows = [];
        foreach ($groups as $group) {
            // Member Persona first: the first seat lands on the first recent Shift, the one
            // left unrecorded, so her My sign-ups panel always carries a past Shift still owed
            // a number (#529, ADR-0023 §5).
            $roster = $this->livingRoster($group, memberFirst: true);
            if ($roster === []) {
                continue;
            }

            $schedule = $this->recentSchedule($group, $now);
            $shifts = $this->recentShifts($schedule, $now);

            $cursor = 0;
            foreach ($shifts as $index => $shift) {
                for ($seat = 0; $seat < self::RECENT_SEATS; $seat++, $cursor++) {
                    $member = $roster[$cursor % count($roster)];
                    $rows[] = $this->seatRecord($group, $shift, $member, $index, $seat, $now);
                }
            }
        }

        $this->writeSeatRecords($rows);
    }

    /**
     * The Group's living roster (Resigned / Deceased excluded — they cannot work a Shift),
     * id-ordered so the seated set is deterministic and the curated Personas, seeded first, take
     * the earliest seats. With `$memberFirst`, the Member Persona moves to the front where she
     * belongs to the Group, so the first seat is hers (#529).
     *
     * @return list<Member>
     */
    private function livingRoster(Group $group, bool $memberFirst = false): array
    {
        $roster = Member::whereHas('memberships', fn ($query) => $query
            ->where('group_id', $group->id)
            ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased]))
            ->orderBy('id')
            ->get();

        if ($memberFirst) {
            $roster = $roster->sortBy(fn (Member $member) => $this->isMemberPersona($member) ? 0 : 1);
        }

        return $roster->values()->all();
    }

    /**
     * The recent, all-past Schedule for a collecting Group — published, spanning the window the
     * recent Shifts sit in. Keyed on (Group, name) so a reseed heals rather than duplicates.
     */
    private function recentSchedule(Group $group, CarbonImmutable $now): Schedule
    {
        return Schedule::firstOrCreate(
            ['group_id' => $group->id, 'name' => self::RECENT_SCHEDULE_NAME],
            [
                'starts_on' => $now->subDays(self::RECENT_SPAN_DAYS)->toDateString(),
                'ends_on' => $now->toDateString(),
                'state' => ScheduleState::Published,
                'description' => 'Recently completed shifts — the visitor numbers filed at sign-out.',
            ],
        );
    }

    /**
     * The recent Shifts on the Schedule — one per {@see RECENT_SHIFT_OFFSETS} entry, each a
     * three-hour morning Shift already ended, built on the org wall clock and stored in UTC
     * ({@see OrgTime}). Keyed on (Schedule, starts_at) so a reseed neither duplicates nor drifts.
     *
     * @return list<Shift>
     */
    private function recentShifts(Schedule $schedule, CarbonImmutable $now): array
    {
        $shifts = [];
        foreach (self::RECENT_SHIFT_OFFSETS as $offset) {
            $day = $now->subDays($offset);
            $startsAt = OrgTime::toUtc($day->setTime(10, 0)->toDateTimeString());
            $endsAt = OrgTime::toUtc($day->setTime(13, 0)->toDateTimeString());

            $shifts[] = Shift::firstOrCreate(
                ['schedule_id' => $schedule->id, 'starts_at' => $startsAt],
                [
                    'ends_at' => $endsAt,
                    'capacity' => self::RECENT_SEATS,
                    'audience' => ShiftAudience::Group,
                    'shift_kind_id' => null,
                ],
            );
        }

        return $shifts;
    }

    /**
     * One seat's after-the-shift record, as an upsert row. The first Shift of the Schedule is left
     * unrecorded — null on every column — because a null `visitor_count` is the outstanding marker
     * (ADR-0023 §5). Every later seat carries a count, a few of them a deliberate recorded zero
     * ("nobody came"), distinct from the null. A tour-leading Group also fills the second box
     * ({@see Group::$collects_extra_interactions}).
     *
     * Deterministic ({@see spread()}, not `fake()`) so a local reseed and a staging deploy read
     * identically. Every visitor column is present on every row — null where the Group does not
     * collect it — so the whole set upserts under one uniform column list.
     *
     * @return array<string, int|CarbonImmutable|null>
     */
    private function seatRecord(Group $group, Shift $shift, Member $member, int $shiftIndex, int $seat, CarbonImmutable $now): array
    {
        $row = [
            'shift_id' => $shift->id,
            'member_id' => $member->id,
            'visitor_count' => null,
            'extra_interaction_count' => null,
            'visitors_france_europe' => null,
            'visitors_quebec' => null,
            'visitors_toronto' => null,
            'visitors_rest_of_canada' => null,
            'visitors_other_countries' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // The first Shift stays outstanding: nobody has filed a number, so every seat is null.
        if ($shiftIndex === 0) {
            return $row;
        }

        $seed = $group->id * 6151 + $shiftIndex * 97 + $seat;

        // One seat in ten is a recorded zero — a real "nobody came", not an omission.
        $count = $this->spread($seed, 0, 9) === 0 ? 0 : $this->spread($seed + 1, 5, 40);
        $row['visitor_count'] = $count;

        if ($group->collects_extra_interactions) {
            $row['extra_interaction_count'] = $this->spread($seed + 2, 0, 10);
        }

        return $row;
    }

    /**
     * Split a visitor count into five origins that sum back to it exactly (ADR-0023 §3 — legacy
     * honours this on 712 of 712 rows). An even base, the remainder spread one-per-bucket, then
     * rotated by a seed so the columns are not always front-loaded. A zero count yields five zeros,
     * whose sum is still zero, so the invariant holds for a recorded zero as well.
     *
     * @return list<int>
     */
    private function splitProvenance(int $count, int $seed): array
    {
        $origins = array_fill(0, 5, intdiv($count, 5));
        for ($i = 0, $remainder = $count % 5; $i < $remainder; $i++) {
            $origins[$i]++;
        }

        $rotation = $this->spread($seed, 0, 4);

        return array_merge(array_slice($origins, $rotation), array_slice($origins, 0, $rotation));
    }

    /**
     * Write the seat rows in bulk (#474 keeps #431's rule — never a row at a time). An upsert on the
     * (Shift, Member) grain both seats the Sign-up and files its numbers in one statement, and on a
     * reseed restates each seat to its absolute value rather than doubling. `created_at` is written
     * on insert only; the seven visitor columns and `updated_at` are the healed set.
     *
     * @param  list<array<string, int|CarbonImmutable|null>>  $rows
     */
    private function writeSeatRecords(array $rows): void
    {
        foreach (array_chunk($rows, self::HOURS_CHUNK) as $chunk) {
            SignUp::upsert(
                $chunk,
                ['shift_id', 'member_id'],
                [
                    'visitor_count',
                    'extra_interaction_count',
                    'visitors_france_europe',
                    'visitors_quebec',
                    'visitors_toronto',
                    'visitors_rest_of_canada',
                    'visitors_other_countries',
                    'updated_at',
                ],
            );
        }
    }

    /**
     * Seed the Group's small kind vocabulary (ADR-0021 §3): the Docents' two daily tour
     * labels and the booked Group Tour. A real table so a qualification requirement has
     * somewhere to hang later; the first pass seeds the rows and builds no maintenance CRUD.
     * Ordered by `sort_order`, all active, keyed on (Group, name) so a reseed heals rather
     * than duplicates.
     *
     * @return array<string, ShiftKind>
     */
    private function shiftKinds(Group $group): array
    {
        $kinds = [];
        foreach ([self::HIGHLIGHTS_TOUR, self::GALLERY_TOUR, self::GROUP_TOUR] as $order => $name) {
            $kinds[$name] = ShiftKind::firstOrCreate(
                ['group_id' => $group->id, 'name' => $name],
                ['active' => true, 'sort_order' => $order],
            );
        }

        return $kinds;
    }

    /**
     * Hours go live (PRD #406, ADR-0022). Seeded last — after every Group, Member,
     * membership and Sign-up exists — so a record can hang off a real roster.
     *
     * The reports are the point of the feature, and a report with no rows prints as an
     * empty page, so this fills two fiscal years across the tree: the one that closed,
     * in full, and the one under way, up to the current month (nobody logs hours they
     * have not worked yet). Two years so the fiscal-year picker has somewhere to go.
     *
     * The spread is chosen so each report has something to show:
     * - **Scheduled hours** land only on Groups that run scheduling, so the Summary
     *   Committee Statistics scheduled section has rows and the non-scheduling Groups
     *   are correctly absent from it (story 50).
     * - **Extra hours** land on every Group, scheduling or not, so a committee that
     *   runs no roster still reports work.
     * - **Meeting hours** land on Groups that hold meetings, as rows carrying a
     *   non-zero `meeting_id`, so the Member Meeting Hours summary and the detailed
     *   report's meetings column are not blank (stories 46, 51).
     * - Rows land on the **DMV root itself** and on Groups three levels down, so the
     *   own-versus-subtree rollup shows two different numbers (stories 41, 42, 52).
     * - Every fifth Member records nothing anywhere, so Members with Zero Hours,
     *   Zero Shift Hours and Zero Extra Hours all have names to list (stories 54, 55).
     *
     * Faker-free and idempotent, like the rest of this seeder. Numbers come from
     * {@see spread()} — a deterministic step on the ids, not `fake()`, which is absent
     * from the `--no-dev` staging build — and every row is written to an **absolute**
     * value rather than added to what is on file, so a reseed heals instead of doubling.
     * That is why this does not call {@see HoursRecord::enterExtra()}: the real entry
     * path is deliberately additive (ADR-0022 §2), which is the one thing a re-runnable
     * seeder cannot be.
     *
     * The seeded `scheduled_hours` are final numbers with the Group's `hours_multiplier`
     * already baked in, exactly as the legacy importer's rows are (ADR-0022 §7) —
     * nothing here re-applies it.
     *
     * Unlike the rest of the seeder, this phase writes in bulk rather than a row at a
     * time. Two fiscal years across the tree is a few thousand records, and a
     * `firstOrNew`/`save`/`exists` cycle for each cost about eleven thousand queries a
     * run. A staging deploy seeds once and never notices; the test suite seeds this class
     * dozens of times and paid it every time, which was enough to quadruple CI. The rows
     * built here are the ones the per-row version produced, in the same order, so the ids
     * and the demo data are unchanged.
     */
    private function hours(): void
    {
        $author = Member::where('email', self::CHAIR_EMAIL)->firstOrFail();

        // Never a month still to come: the current fiscal year stops at this month, and
        // the closed one runs its full twelve. Lexical comparison is safe on YYYYMM.
        $thisMonth = OrgTime::now()->format('Ym');
        $currentFiscalYear = OrgTime::currentFiscalYear();

        $months = [];
        foreach ([$currentFiscalYear - 1, $currentFiscalYear] as $fiscalYear) {
            foreach (OrgTime::fiscalYearMonths($fiscalYear) as $yearMonth) {
                if ($yearMonth <= $thisMonth) {
                    $months[] = $yearMonth;
                }
            }
        }

        // Container sections are page-less (PRD #289) and carry no roster, so they carry
        // no hours; every other Group in the tree does, the root included.
        $groups = Group::where('kind', '!=', Kind::Container)->orderBy('id')->get();

        $rosters = $this->hoursRecorders($groups);

        $rows = [];
        foreach ($groups as $group) {
            foreach ($rosters[$group->id] ?? [] as $memberId) {
                foreach ($months as $index => $yearMonth) {
                    foreach ($this->memberMonthHours($group, $memberId, $yearMonth, $index) as $row) {
                        $rows[] = $row;
                    }
                }
            }
        }

        $this->writeHours($rows, $author);
    }

    /**
     * Who records hours in each Group, resolved for the whole tree in one query rather
     * than one a Group.
     *
     * Recorders are drawn from the Group's living roster — never a departed Member, who
     * cannot accrue hours (ADR-0022 §4) — capped at a handful so the fiscal-year matrix
     * prints on a page rather than running to twenty rows a Group. Every fifth Member by
     * id is then held back entirely, so the zero-hours reports have a stable set of names
     * rather than an empty list.
     *
     * @param  EloquentCollection<int, Group>  $groups
     * @return array<int, list<int>> member ids, keyed by Group id
     */
    private function hoursRecorders(EloquentCollection $groups): array
    {
        $memberships = GroupMember::query()
            ->whereIn('group_id', $groups->modelKeys())
            ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased])
            ->orderBy('member_id')
            ->get(['group_id', 'member_id'])
            ->groupBy('group_id');

        $recorders = [];
        foreach ($memberships as $groupId => $rows) {
            $recorders[(int) $groupId] = $rows
                ->pluck('member_id')
                ->take(self::HOURS_RECORDERS)
                ->reject(fn (int $memberId): bool => $memberId % 5 === 0)
                ->values()
                ->all();
        }

        return $recorders;
    }

    /**
     * One Member's hours in one Group for one month — the grain (ADR-0022 §1). Roughly
     * two months in three carry a row, so the twelve-month matrix has the gaps a real
     * year has rather than reading as a solid block. A month that also falls to a meeting
     * yields a second row carrying a non-zero `meeting_id`, which is how meeting hours are
     * told apart from ordinary extra hours (§1, the importer's note).
     *
     * The meeting id is synthetic. This seeder creates no Meetings and meeting-hours entry
     * is out of the first pass (§2), so the row carries exactly the shape the legacy
     * importer produces for a meeting with no v2 counterpart: a non-zero id nothing joins
     * to. The reports only ask whether it is the no-meeting sentinel, so they read
     * correctly. It is derived from the month, so a reseed heals the same row rather than
     * adding a second.
     *
     * `total_hours` is not a free field: it is written as the sum of the pair, so the
     * seeded rows satisfy the same identity every write path does (§1). `extra_interactions`
     * (ADR-0023 §6) sits outside that sum — a visitor count, not hours — and lands only on the
     * Groups whose sole route into the visitor report is the Hours tab ({@see EXTRA_INTERACTION_GROUPS}).
     *
     * @return list<array<string, int|string>>
     */
    private function memberMonthHours(Group $group, int $memberId, string $yearMonth, int $index): array
    {
        $seed = $memberId * 977 + $group->id * 31 + $index;

        if ($this->spread($seed, 0, 2) === 0) {
            return [];
        }

        $scheduled = $group->has_scheduling ? $this->spread($seed + 1, 0, 12) : 0;
        $extra = $this->spread($seed + 2, 0, 6);

        // Extra interactions (ADR-0023 §6): a whole visitor count typed on the Hours tab, outside
        // total_hours. Seeded on the Groups whose only route into Summary Visitor Interactions is
        // this column, so a fresh seed shows them there — ROM Travel's entire presence is here.
        $interactions = in_array($group->slug, self::EXTRA_INTERACTION_GROUPS, true)
            ? $this->spread($seed + 5, 15, 80)
            : 0;

        if ($scheduled === 0 && $extra === 0 && $interactions === 0) {
            return [];
        }

        $rows = [[
            'member_id' => $memberId,
            'group_id' => $group->id,
            'year_month' => $yearMonth,
            'meeting_id' => HoursRecord::NO_MEETING,
            'scheduled_hours' => $scheduled,
            'extra_hours' => $extra,
            'total_hours' => $scheduled + $extra,
            'extra_interactions' => $interactions,
        ]];

        // The meeting row gets no adjustment: the trail records a Member's own testimony,
        // and meeting hours come from an attendance roster, not from the entry form.
        if ($group->has_meetings && $this->spread($seed + 3, 0, 3) === 0) {
            $meetingExtra = $this->spread($seed + 4, 1, 3);

            $rows[] = [
                'member_id' => $memberId,
                'group_id' => $group->id,
                'year_month' => $yearMonth,
                'meeting_id' => self::MEETING_ID_BASE + (int) $yearMonth % 100,
                'scheduled_hours' => 0,
                'extra_hours' => $meetingExtra,
                'total_hours' => $meetingExtra,
                'extra_interactions' => 0,
            ];
        }

        return $rows;
    }

    /**
     * Write the built rows, then the append-only trail behind their extra hours
     * (ADR-0022 §6).
     *
     * The records go in as an upsert on the uniqueness grain, so a reseed restates a row
     * to its absolute value rather than doubling it — the same healing `firstOrNew` gave,
     * at one statement a chunk instead of two queries a row. They are written in build
     * order, so a first seed hands out the ids the per-row version did.
     *
     * Adjustments are written only where a record has none, because the log is
     * append-only and a reseed must not lengthen it. Every seventh record carries a
     * correction as well as its original entry, so the log shows the shape it has in
     * life: an overstatement and its negative fix, the two deltas still summing to the
     * record's `extra_hours`.
     *
     * Both passes are scoped to the rows built above rather than to the whole table, so
     * this reaches exactly as far as the per-row version did and never rewrites a record
     * some other seeder or test put there.
     *
     * @param  list<array<string, int|string>>  $rows
     */
    private function writeHours(array $rows, Member $author): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();

        foreach (array_chunk($rows, self::HOURS_CHUNK) as $chunk) {
            HoursRecord::upsert(
                array_map(
                    fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
                    $chunk,
                ),
                ['member_id', 'group_id', 'year_month', 'meeting_id'],
                ['scheduled_hours', 'extra_hours', 'total_hours', 'extra_interactions', 'updated_at'],
            );
        }

        // The rows whose extra hours need a trail, as a lookup on the grain: an upsert
        // hands back no ids, and on a reseed the rows already carried ids of their own,
        // so the ids are read back rather than tracked through the write.
        $owed = [];
        foreach ($rows as $row) {
            if ($row['meeting_id'] === HoursRecord::NO_MEETING && $row['extra_hours'] > 0) {
                $owed[$row['member_id'].':'.$row['group_id'].':'.$row['year_month']] = true;
            }
        }

        $records = HoursRecord::query()
            ->where('meeting_id', HoursRecord::NO_MEETING)
            ->whereIn('group_id', array_unique(array_column($rows, 'group_id')))
            ->get(['id', 'member_id', 'group_id', 'year_month', 'extra_hours'])
            ->filter(fn (HoursRecord $record): bool => isset(
                $owed[$record->member_id.':'.$record->group_id.':'.$record->year_month]
            ));

        $logged = HoursAdjustment::query()
            ->whereIn('hours_record_id', $records->modelKeys())
            ->distinct()
            ->pluck('hours_record_id')
            ->flip();

        $adjustments = [];
        foreach ($records as $record) {
            if ($logged->has($record->id)) {
                continue;
            }

            $deltas = $record->id % 7 === 0
                ? [$record->extra_hours + 2, -2]
                : [$record->extra_hours];

            foreach ($deltas as $delta) {
                $adjustments[] = [
                    'hours_record_id' => $record->id,
                    'delta' => $delta,
                    'created_by' => $author->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($adjustments, self::HOURS_CHUNK) as $chunk) {
            HoursAdjustment::insert($chunk);
        }
    }

    /**
     * A few items on the org-wide feed (#155), so the News page shows a feed rather
     * than "No news yet" — the help screenshots (#528) read it. Posted by
     * Communications, the standing committee with announcements on and the Group the
     * news-editor Persona posts for. Keyed on (Group, title) so a reseed heals rather
     * than duplicates; dated a few days apart so the feed shows a spread of dates.
     */
    private function news(): void
    {
        $communications = $this->findGroup('communications');

        if ($communications === null) {
            return;
        }

        $now = OrgTime::now();

        foreach ($this->newsItems() as $index => [$title, $body]) {
            $item = News::firstOrNew(['posting_group_id' => $communications->id, 'title' => $title]);
            $item->body = $body;
            $item->created_at ??= $now->subDays(1 + $index * 3);
            $item->save();
        }
    }

    /**
     * Two meetings on the Executive committee (#530), so its Meetings tab shows real cards;
     * the Record a meeting walkthrough shoots it as the Executive Secretary. One is ahead with
     * an agenda, one is behind with an agenda and minutes. Dated from today on the org wall
     * clock and keyed on (Group, title), so a reseed moves the dates rather than duplicating
     * the rows.
     */
    private function meetings(): void
    {
        $executive = $this->findGroup('executive');

        if ($executive === null || ! $executive->has_meetings) {
            return;
        }

        $today = OrgTime::now()->startOfDay();

        foreach ($this->meetingSpecs($today) as $spec) {
            $meeting = Meeting::updateOrCreate(
                ['group_id' => $executive->id, 'title' => $spec['title']],
                [
                    'held_at' => $spec['held_at']->utc(),
                    'description' => $spec['description'],
                    'location' => 'Volunteer lounge, Level 1',
                    'video_url' => 'https://example.com/dmv-executive',
                    'is_published' => true,
                ],
            );

            foreach ($spec['links'] as $link) {
                MeetingLink::updateOrCreate(
                    ['meeting_id' => $meeting->id, 'kind' => $link],
                    ['url' => "https://example.com/dmv-executive/{$meeting->id}/{$link->value}"],
                );
            }
        }
    }

    /**
     * The seeded meetings, the one ahead first.
     *
     * @return list<array{title: string, held_at: CarbonImmutable, description: string, links: list<MeetingLinkKind>}>
     */
    private function meetingSpecs(CarbonImmutable $today): array
    {
        return [
            [
                'title' => 'Monthly Executive meeting',
                'held_at' => $today->addDays(9)->setTime(10, 0),
                'description' => 'Standing monthly meeting. Committee reports, the fall volunteer fair, and the budget update.',
                'links' => [MeetingLinkKind::Agenda],
            ],
            [
                'title' => 'Summer planning meeting',
                'held_at' => $today->subDays(21)->setTime(10, 0),
                'description' => 'Planning for the fall term: orientation dates, program recruitment, and the Directory photo drive.',
                'links' => [MeetingLinkKind::Agenda, MeetingLinkKind::Minutes],
            ],
        ];
    }

    /**
     * The seeded feed, newest first. Plain notices a Group would really post.
     *
     * @return list<array{string, string}>
     */
    private function newsItems(): array
    {
        return [
            [
                'Fall orientation for new Volunteers',
                'New Volunteers start the fall term with a morning orientation in the volunteer lounge. Returning Members are welcome to sit in. Coffee is ready from 9 a.m., and the session runs from 9:30 to noon.',
            ],
            [
                'Add your photo to the Directory',
                'The Directory now shows a photo beside every name. Open My Profile from your initials in the top bar and add a recent photo. A square head-and-shoulders shot works best.',
            ],
            [
                'Extra hours are due at month end',
                'Record any hours you worked outside your scheduled Shifts before the month closes. Open the Hours tab on your Group to add them. Scheduled Shifts and meetings are already counted.',
            ],
        ];
    }

    /**
     * A deterministic number in `[$min, $max]` from a seed — this seeder's stand-in for
     * `fake()`, which is a dev-only dependency absent from the `--no-dev` staging build.
     * The same seed always gives the same number, so a local reseed and a staging deploy
     * read identically, and a screenshot taken today still matches next week.
     *
     * `crc32` rather than a linear step, because a linear step is not mixed enough for
     * this. A classic LCG multiplier shares small factors with the ranges here, so
     * consecutive seeds walk a short repeating cycle — which in a twelve-month matrix
     * shows up as whole columns of zeros, exactly the thing a demo report must not have.
     */
    private function spread(int $seed, int $min, int $max): int
    {
        return $min + crc32((string) $seed) % ($max - $min + 1);
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
                // Governance & Operations — a container section grouping the
                // org-level standing committees; not itself a Group (PRD #289).
                $this->container('Governance & Operations', [
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
                // Programs — a container section grouping the member-facing
                // operating units, with their working groups and exhibition cohorts.
                $this->container('Programs', [
                    // Docents lead tours, so they collect the split — the visitor count and
                    // the talked-to second box (ADR-0023 §2) — and their historical figures came
                    // off a booking table, so they carry the incomplete marker (§6).
                    $this->program('Docents', [
                        $this->cohort('Pompeii', archived: true),
                        $this->cohort('Ultimate Dinosaurs', archived: true),
                        $this->cohort('Forbidden City', archived: true),
                    ], GroupLogo::Docents, capabilities: [
                        'collects_visitor_count' => true,
                        'collects_extra_interactions' => true,
                        'visitor_figures_await_booking' => true,
                        // Reminders on with the standard 3 lead days (ADR-0024 §7) — one of the
                        // five Groups that run them today. The lead days stay at the column
                        // default, so only the switch is set here.
                        'reminders_enabled' => true,
                    ]),
                    // GDR is the one Group in fifteen years with visitor provenance (ADR-0023 §3):
                    // it collects the count, the split, the five origins, and the booking marker.
                    $this->program('Guides du ROM', [], GroupLogo::GuidesDuRom, capabilities: [
                        'collects_visitor_count' => true,
                        'collects_extra_interactions' => true,
                        'collects_visitor_provenance' => true,
                        'visitor_figures_await_booking' => true,
                        'reminders_enabled' => true,
                    ]),
                    $this->program('Les Amis Francophiles', [], GroupLogo::LesAmisFrancophiles),
                    $this->program('DMV Hands-on Tours', [
                        $this->workingGroup('Social', 'hands-on-tours-social'),
                        $this->workingGroup('Training', 'hands-on-tours-training'),
                        $this->workingGroup('Vetting', visibility: ListingVisibility::Public),
                    ], GroupLogo::DmvHandsOnTours),
                    // Gallery Interpreters work the galleries, not tours: they collect one
                    // number, the visitor count, which already is an interaction count (§2).
                    $this->program('Gallery Interpreters', [
                        // A Private subgroup nested in a Program — the strictest
                        // tier, exercised on staging (PRD #268, ADR-0019).
                        $this->workingGroup('Events', 'gallery-interpreters-events', ListingVisibility::Private),
                    ], GroupLogo::GalleryInterpreters, capabilities: [
                        'collects_visitor_count' => true,
                    ]),
                    // ROMForYou deliberately ships no mark — the visible generic
                    // fallback the launcher exercises on a top-level program (#257).
                    // ROMForYou runs desk-style presentations — one visitor count — and its
                    // legacy figure came off a booking table most severely of all (§6: 293 of
                    // 1,055 in fiscal 2026), so it flies the incomplete marker.
                    $this->program('ROMForYou', [
                        $this->workingGroup('Content Development', visibility: ListingVisibility::Public),
                        $this->workingGroup('Team Leads — adult presentations'),
                        $this->workingGroup('Outreach'),
                        $this->workingGroup('Adapted Presentations'),
                    ], capabilities: [
                        'collects_visitor_count' => true,
                        'visitor_figures_await_booking' => true,
                    ]),
                    // Visitor Guides and Visitor Wayfinders staff desks: one visitor count each,
                    // no second box (§2). They are the forced-entry Groups behind legacy's 96-98%.
                    // Visitor Guides runs the empty-desk alert in legacy — its Desk kind is the
                    // one watched kind, seeded on in scheduling() (#487, ADR-0024 §7).
                    $this->program('Visitor Guides', [], GroupLogo::VisitorGuides, capabilities: [
                        'collects_visitor_count' => true,
                        'reminders_enabled' => true,
                        'empty_desk_alert_enabled' => true,
                    ]),
                    $this->program('Visitor Wayfinders', [
                        $this->workingGroup('Documentation', visibility: ListingVisibility::Public),
                        $this->workingGroup('Shadow Shift & Vetting Volunteers'),
                        $this->workingGroup('Social Committee'),
                        $this->cohort('OSIRIS REX VOLUNTEERS', stale: true),
                        $this->cohort('TRex Spot Tours', archived: true),
                        $this->cohort('Blue Whale', archived: true),
                        $this->cohort('Zuul', archived: true),
                    ], GroupLogo::VisitorWayfinders, capabilities: [
                        'collects_visitor_count' => true,
                        'reminders_enabled' => true,
                    ]),
                    // ROMWalks — one coherent subtree merged from the source's
                    // two differing listings (see class docblock).
                    $this->program('ROMWalks', [
                        $this->workingGroup('Brochure Committee'),
                        $this->workingGroup('Education', visibility: ListingVisibility::Public),
                        $this->workingGroup('PR Committee'),
                        $this->workingGroup('Script Vetting'),
                        $this->workingGroup('Statistical', visibility: ListingVisibility::Public),
                        $this->workingGroup('Training', 'romwalks-training'),
                        $this->workingGroup('Walker Vetting'),
                    ], GroupLogo::Romwalks, capabilities: [
                        // A walk is a led tour, so ROMWalks collects the split; its figures came
                        // off a booking table too, so it carries the incomplete marker (§6).
                        'collects_visitor_count' => true,
                        'collects_extra_interactions' => true,
                        'visitor_figures_await_booking' => true,
                    ], hoursMultiplier: 2),
                    $this->program('Reception', [
                        $this->workingGroup('Library', visibility: ListingVisibility::Public),
                    ], GroupLogo::Reception, capabilities: [
                        'reminders_enabled' => true,
                    ]),
                    // ROMBus is a booking-only Group — scheduling stays off until the
                    // group-booking capability lands (#364, ADR-0021).
                    $this->program('ROMBus', [], GroupLogo::Rombus, ['has_scheduling' => false]),
                    // ROM Travel has no scheduling table in production at all (ADR-0023, facts
                    // for the migration plan), so scheduling stays off and its only presence in
                    // the visitor report is hand-typed extra interactions on the Hours tab (§6).
                    $this->program('ROMTravel', [
                        $this->workingGroup('Admin Committee'),
                        $this->workingGroup('Feasibility Committee'),
                        $this->workingGroup('Support Roles', visibility: ListingVisibility::Public),
                    ], GroupLogo::Romtravel, ['has_scheduling' => false]),
                ]),
                // Special Projects — a real coordinating Group (not a container):
                // it has a page, a roster and leadership like any standing committee,
                // and organizes the cross-program projects beneath it (PRD #289).
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
                // Friends — a container section, structural peer of Governance &
                // Operations / Programs / Special Projects. Not a naming heuristic:
                // Bishop White (FEA) doesn't begin with "Friends of". Associated
                // Friends stays under Governance & Operations — it is a distinct
                // coordinating committee, not this container.
                $this->container('Friends', [
                    $this->sc('Bishop White (FEA)', logo: GroupLogo::BishopWhiteFea),
                    $this->sc('Friends of Global South Asia (FSA)', logo: GroupLogo::FriendsOfGlobalSouthAsiaFsa),
                    $this->sc('Friends of Textiles & Costume', [
                        $this->workingGroup('Adopt-a-Journal'),
                        $this->workingGroup('Donor Friends', visibility: ListingVisibility::Public),
                        $this->workingGroup('Education SubCommittee'),
                        $this->workingGroup('Newsletter SubCommittee'),
                        $this->workingGroup('Programs & Events'),
                    ], logo: GroupLogo::FriendsOfTextilesCostume),
                    $this->sc('Friends of Palaeontology (FOP)', [
                        $this->workingGroup('Vertebrate Palaeontology'),
                    ], logo: GroupLogo::FriendsOfPalaeontologyFop),
                    $this->sc('Friends of Earth & Space (FES)', logo: GroupLogo::FriendsOfEarthSpaceFes),
                ]),
            ],
        ];
    }

    /**
     * A container section node (PRD #289): an organization-scope grouping that
     * has no page, no roster and no capabilities — pure scaffolding that explodes
     * to the members-facing Groups beneath it. Modelled as {@see Kind::Container},
     * the stored, queryable marker the page-gate, rail and launcher slices read
     * from (retiring the demo-only {@see structuralSlugs()} guessing).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function container(string $name, array $children = []): array
    {
        return ['name' => $name, 'kind' => Kind::Container, 'children' => $children];
    }

    /**
     * A standing-committee node — a real coordinating Group (the org-level section
     * containers are separate, see {@see container()}). `capabilities` overrides
     * specific Kind-derived flags for the rare node whose capability profile differs
     * (e.g. Communications turning on announcements so the news-editor Persona's
     * `post-news` gate is satisfiable).
     *
     * `logo` gives a Friends-of committee its own identity mark on the launcher
     * (PRD #253); most standing committees stay null and fall back.
     * `visibility` overrides the Kind-derived default ({@see defaultVisibilityFor});
     * standing committees are Public by default, so it is rarely passed here.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, bool>  $capabilities
     * @return array<string, mixed>
     */
    private function sc(string $name, array $children = [], array $capabilities = [], ?GroupLogo $logo = null, ?ListingVisibility $visibility = null): array
    {
        return [
            'name' => $name,
            'kind' => Kind::StandingCommittee,
            'children' => $children,
            'capabilities' => $capabilities,
            'logo' => $logo,
            'visibility' => $visibility,
        ];
    }

    /**
     * A program node. Pass a logo to give it its own identity mark on the
     * launcher (PRD #253); most programs stay null and show the generic fallback.
     * `capabilities` overrides specific Kind-derived flags — ROMBus turns scheduling
     * off, since its only shape is group booking, deferred out of the first pass (#364).
     * `hoursMultiplier` sets the walks-to-hours ratio (ADR-0022 §7); only ROMWalks
     * passes a value other than the default 1.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, bool>  $capabilities
     * @return array<string, mixed>
     */
    private function program(string $name, array $children = [], ?GroupLogo $logo = null, array $capabilities = [], int $hoursMultiplier = 1): array
    {
        return ['name' => $name, 'kind' => Kind::Program, 'children' => $children, 'logo' => $logo, 'capabilities' => $capabilities, 'hours_multiplier' => $hoursMultiplier];
    }

    /**
     * A working-group node. Pass an explicit slug to disambiguate names that
     * recur across the tree (e.g. "Training" under two programs); pass
     * `visibility` to hide it (e.g. Gallery Interpreters → Events is Private).
     *
     * @return array<string, mixed>
     */
    private function workingGroup(string $name, ?string $slug = null, ?ListingVisibility $visibility = null): array
    {
        $node = ['name' => $name, 'kind' => Kind::WorkingGroup, 'visibility' => $visibility];

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
            // A container has no page, so no auto-generated About blurb (PRD #289).
            'description' => $node['description'] ?? ($kind === Kind::Container ? null : $this->aboutFor($node)),
            'kind' => $kind,
            'scope' => $this->scopeFor($kind),
            // Listing visibility (PRD #268, ADR-0019). The recruiting/scaffold nodes
            // (org root, section containers, programs, projects, cohorts, standing
            // committees) are org-listed Public by Kind; only internal working
            // subgroups fall to the fail-closed `Group` default. A node may still
            // override — Gallery Interpreters → Events is hand-set Private — so
            // staging exercises all three tiers.
            'listing_visibility' => $node['visibility'] ?? $this->defaultVisibilityFor($kind),
            'display_order' => $order,
            'lifecycle_state' => LifecycleState::Active,
            'time_boxed' => false,
            'start_date' => null,
            'end_date' => null,
            // Identity mark on the launcher (PRD #253); null → generic fallback,
            // which is the common case across the demo tree.
            'logo_key' => $node['logo'] ?? null,
            // Walks-to-hours ratio (ADR-0022 §7): 1 everywhere but the walking tours,
            // which count each walk as two hours.
            'hours_multiplier' => $node['hours_multiplier'] ?? 1,
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
            // A container has no page and so no About blurb; {@see attributesFor}
            // gives it a null description. This arm only keeps the match exhaustive.
            Kind::Container => '',
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
            Kind::StandingCommittee, Kind::Container => Scope::Organization,
            Kind::Program, Kind::Cohort => Scope::Program,
            Kind::WorkingGroup, Kind::Project => Scope::Subteam,
        };
    }

    /**
     * The fail-closed listing visibility a node gets when it doesn't override one
     * (PRD #268, ADR-0019). Internal working subgroups default to `Group` — pruned
     * from outsiders, shown to their parent Group's members. Everything else — the
     * org root, its section containers, programs, projects, cohorts, and standing
     * committees — is `Public`: the recruiting/scaffold tree every Member browses,
     * matching today's org-open behaviour. Nobody is a "member" of the structural
     * containers, so leaving them at `Group` would prune the whole tree away.
     */
    private function defaultVisibilityFor(Kind $kind): ListingVisibility
    {
        return $kind === Kind::WorkingGroup
            ? ListingVisibility::Group
            : ListingVisibility::Public;
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
        ];

        return match ($kind) {
            Kind::StandingCommittee => ['has_meetings' => true, 'has_documents' => true] + $off,
            Kind::Program => [
                'has_documents' => true,
                'has_scheduling' => true,
                'has_content_catalog' => true,
            ] + $off,
            Kind::WorkingGroup => ['has_meetings' => true] + $off,
            Kind::Project => ['has_documents' => true] + $off,
            Kind::Cohort => ['has_scheduling' => true] + $off,
            // A container is pure scaffolding — every capability stays off.
            Kind::Container => $off,
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
