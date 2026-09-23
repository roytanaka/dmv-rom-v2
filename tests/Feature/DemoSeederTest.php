<?php

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
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\HandlingObject;
use App\Models\HoursRecord;
use App\Models\Meeting;
use App\Models\MeetingLink;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Personas\PersonaCatalogue;
use App\Support\CommitteeHoursStatistics;
use App\Support\OrgTime;
use App\Support\VisitorInteractionStatistics;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The curated demo data (PRD #139, slice 2 / #141): the faker-free, idempotent
 * full DMV org tree seeded into staging by hand before a board pitch. These
 * assertions check external, observable invariants — the tree resolves at real
 * depth, every Group Kind is present, archived/stale nodes drop out of the
 * active filter, and re-seeding heals rather than duplicates — rather than
 * specific curated content, so the suite survives the org list changing.
 */

// Demo seeding fetches best-effort DiceBear avatars; fake the HTTP client so these
// org-tree assertions never touch the network. An empty 200 leaves every Member on
// the initials fallback, which these tests don't assert against.
beforeEach(function () {
    Http::fake();
    $this->seed(DemoSeeder::class);
});

it('builds a tree whose relationships resolve from the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->with(['parent', 'children.parent'])->firstOrFail();

    expect($root->parent)->toBeNull()
        ->and($root->children)->not->toBeEmpty()
        ->and($root->children->every(fn (Group $child) => $child->parent->is($root)))->toBeTrue();
});

it('anchors stable handles for a committee and a program under the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $committee = Group::where('slug', DemoSeeder::COMMITTEE)->firstOrFail();
    $program = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();

    expect($committee->parent->is($root))->toBeTrue()
        ->and($program->kind)->toBe(Kind::Program)
        ->and($program->parent)->not->toBeNull()
        ->and($program->parent->parent->is($root))->toBeTrue();
});

it('represents every Group Kind in the curated tree', function () {
    foreach (Kind::cases() as $kind) {
        expect(Group::where('kind', $kind)->exists())->toBeTrue();
    }
});

it('resolves working groups and cohorts up to their program at depth', function () {
    $workingGroupUnderProgram = Group::where('kind', Kind::WorkingGroup)->with('parent')->get()
        ->first(fn (Group $g) => $g->parent?->kind === Kind::Program);

    $cohortUnderProgram = Group::where('kind', Kind::Cohort)->with('parent')->get()
        ->first(fn (Group $g) => $g->parent?->kind === Kind::Program);

    expect($workingGroupUnderProgram)->not->toBeNull()
        ->and($cohortUnderProgram)->not->toBeNull();
});

it('excludes archived and stale groups from the active scope but keeps live ones', function () {
    $activeIds = Group::active()->pluck('id');

    $archived = Group::where('lifecycle_state', LifecycleState::Archived)->get();
    expect($archived)->not->toBeEmpty();
    $archived->each(fn (Group $g) => expect($activeIds->contains($g->id))->toBeFalse());

    // An active-but-stale pool: lifecycle Active, but its time-boxed window lapsed.
    $stale = Group::where('lifecycle_state', LifecycleState::Active)
        ->where('time_boxed', true)
        ->whereDate('end_date', '<', now())
        ->get();
    expect($stale)->not->toBeEmpty();
    $stale->each(fn (Group $g) => expect($activeIds->contains($g->id))->toBeFalse());

    $liveProgram = Group::where('kind', Kind::Program)
        ->where('lifecycle_state', LifecycleState::Active)
        ->firstOrFail();
    expect($activeIds->contains($liveProgram->id))->toBeTrue();
});

it('groups the five Friends-of committees under a rosterless Friends container peer', function () {
    // Explicit structure, not a naming heuristic — Bishop White (FEA) wouldn't
    // match a "Friends of …" name filter (PRD #275, ADR-0020 §G).
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $friends = Group::where('slug', 'friends')->with('children')->firstOrFail();

    expect($friends->parent_id)->toBe($root->id)
        ->and($friends->kind)->toBe(Kind::Container);

    $expected = [
        'bishop-white-fea',
        'friends-of-global-south-asia-fsa',
        'friends-of-textiles-costume',
        'friends-of-palaeontology-fop',
        'friends-of-earth-space-fes',
    ];

    expect($friends->children->pluck('slug')->sort()->values()->all())
        ->toBe(collect($expected)->sort()->values()->all());

    expect(GroupMember::where('group_id', $friends->id)->count())->toBe(0);

    // Associated Friends is a distinct coordinating committee and stays under
    // Governance & Operations — it is not the container and is not re-parented.
    $associated = Group::where('slug', 'associated-friends')->firstOrFail();
    expect($associated->parent->slug)->toBe(DemoSeeder::COMMITTEE);
});

it('tags the three org-level section peers as containers with zero members', function () {
    // The container marker (PRD #289, #292): pure scaffolding, no roster, no
    // leadership — clicking one shows the Groups it organizes, not people.
    foreach (['governance-operations', 'programs', 'friends'] as $slug) {
        $container = Group::where('slug', $slug)->firstOrFail();

        expect($container->kind)->toBe(Kind::Container)
            ->and($container->scope)->toBe(Scope::Organization)
            ->and($container->description)->toBeNull()
            ->and($container->has_meetings)->toBeFalse()
            ->and($container->has_documents)->toBeFalse()
            ->and($container->has_scheduling)->toBeFalse()
            ->and(GroupMember::where('group_id', $container->id)->count())->toBe(0)
            ->and(GroupMemberRole::whereRelation('groupMember', 'group_id', $container->id)->count())->toBe(0);
    }
});

it('keeps special-projects a real Group with a roster and leadership', function () {
    // special-projects is promoted out of the structural set (PRD #289, #292): it
    // stays a standing committee with a page, members, and a Chair + Secretary.
    $specialProjects = Group::where('slug', 'special-projects')->firstOrFail();

    expect($specialProjects->kind)->toBe(Kind::StandingCommittee)
        ->and(GroupMember::where('group_id', $specialProjects->id)->count())->toBeGreaterThan(0);

    $officerRoles = GroupMemberRole::whereRelation('groupMember', 'group_id', $specialProjects->id)
        ->pluck('role');

    expect($officerRoles)->toContain(Role::Chair)
        ->and($officerRoles)->toContain(Role::Secretary);
});

it('hand-assigns curated logo keys per node, leaving groups without a mark on the fallback', function () {
    // A representative program, a Friends-of committee, and a "filename ≠ key"
    // rename (special.svg → Visitor Wayfinders) — the per-node assignment (#257).
    expect(Group::where('slug', 'docents')->firstOrFail()->logo_key)->toBe(GroupLogo::Docents)
        ->and(Group::where('slug', 'bishop-white-fea')->firstOrFail()->logo_key)->toBe(GroupLogo::BishopWhiteFea)
        ->and(Group::where('slug', 'visitor-wayfinders')->firstOrFail()->logo_key)->toBe(GroupLogo::VisitorWayfinders);

    // ROMForYou deliberately ships no mark, and the structural root never does —
    // so the generic fallback is visibly exercised on staging (at least one null).
    expect(Group::where('slug', 'romforyou')->firstOrFail()->logo_key)->toBeNull()
        ->and(Group::where('slug', DemoSeeder::ROOT)->firstOrFail()->logo_key)->toBeNull()
        ->and(Group::whereNull('logo_key')->exists())->toBeTrue();
});

it('hand-assigns listing visibility per node so staging exercises all three tiers', function () {
    // The recruiting/scaffold tree is Public (org root, a program, a committee), an
    // internal working subgroup falls to the fail-closed Group default, and one
    // subgroup is hand-set Private (PRD #268, ADR-0019).
    expect(Group::where('slug', DemoSeeder::ROOT)->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'docents')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'membership')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        // A working group hand-flipped to Public so the Public path is exercised on a
        // nested subgroup, not just the recruiting/scaffold tree.
        ->and(Group::where('slug', 'donor-friends')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'pr-committee')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Group)
        ->and(Group::where('slug', 'gallery-interpreters-events')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Private);
});

it('shows a non-empty Other Groups to an ordinary Member with no memberships', function () {
    // Regression (PRD #268): the visibility prune must not swallow the whole Other
    // Groups zone. A plain Member — no memberships, not super-tier — still browses the
    // Public recruiting tree, now surfaced as the four organization-scope container
    // peers (ADR-0020 §C). The bug left the root and its containers at the Group
    // default, pruning every non-member's tree to nothing.
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The first peer is Governance & Operations, a chrome-labelled container.
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.governance_operations')
            // Index 1 existing proves ≥2 container peers survived the prune — the
            // tree did not collapse to nothing for a non-member.
            ->has('rail.otherGroups.items.1'));
});

it('represents varied membership statuses including Full, Trainee and LOA', function () {
    $present = GroupMember::query()->distinct()->pluck('status');

    foreach ([MembershipStatus::Full, MembershipStatus::Trainee, MembershipStatus::Loa] as $status) {
        expect($present->contains($status))->toBeTrue();
    }
});

it('sets an LOA window on every membership on leave', function () {
    $onLeave = GroupMember::where('status', MembershipStatus::Loa)->get();

    expect($onLeave)->not->toBeEmpty();
    $onLeave->each(function (GroupMember $membership) {
        expect($membership->loa_start)->not->toBeNull()
            ->and($membership->loa_end)->not->toBeNull();
    });
});

it('resolves a capability-backed role on a Group whose capability is enabled', function () {
    $role = GroupMemberRole::with('groupMember.group')->get()
        ->first(fn (GroupMemberRole $role) => $role->role->requiredCapability() !== null);

    expect($role)->not->toBeNull();

    $capability = $role->role->requiredCapability();
    expect($role->groupMember->group->{$capability})->toBeTrue();
});

it('resolves a Group stewarding an org-wide function via the stewardship helper', function () {
    $steward = Group::stewardOf(StewardshipFunction::MemberAdmin);

    expect($steward)->not->toBeNull()
        ->and($steward->stewardships()->where('function', StewardshipFunction::MemberAdmin)->exists())->toBeTrue();
});

it('leaves no membership orphaned — every one references a real Group and Member', function () {
    GroupMember::with(['group', 'member'])->get()->each(function (GroupMember $membership) {
        expect($membership->group)->not->toBeNull()
            ->and($membership->member)->not->toBeNull();
    });
});

it('is idempotent — re-seeding leaves row counts unchanged', function () {
    $counts = fn () => [
        'groups' => Group::count(),
        'members' => Member::count(),
        'memberships' => GroupMember::count(),
        'roles' => GroupMemberRole::count(),
        'stewardships' => GroupStewardship::count(),
    ];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});

/*
 * Persona catalogue + seeding (PRD #220 / #221, ADR-0009 dev half). The switcher
 * stands on a code catalogue that is the single source of truth for the curated
 * Personas; these assert the seeded outcomes the switcher and its gates depend on,
 * through observable model/gate behaviour — not the catalogue's internals.
 */

it('seeds exactly the catalogued Personas', function () {
    foreach (PersonaCatalogue::all() as $persona) {
        expect(Member::where('email', $persona->email)->exists())->toBeTrue();
    }
});

it('seeds a super-tier trio in the Executive Group', function () {
    $executive = Group::where('slug', 'executive')->firstOrFail();

    $supers = Member::where('super_tier', true)->with('memberships')->get();

    expect($supers)->toHaveCount(3);
    $supers->each(fn (Member $member) => expect($member->membershipIn($executive))->not->toBeNull());
});

it('seeds the support operator with operator access split cleanly from super-tier', function () {
    // The operator/authority split: exactly one support operator, holding no
    // super-tier org authority; and none of the super-tier executives is an operator.
    $operators = Member::where('support_operator', true)->get();

    expect($operators)->toHaveCount(1);
    expect($operators->first()->isAllDmv())->toBeFalse();
    expect(Member::where('super_tier', true)->where('support_operator', true)->exists())->toBeFalse();
});

it('satisfies canPostNews for the news-editor Persona on an announcements Group', function () {
    $editor = Member::where('email', 'nadia.haddad@dmv.test')
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    $communications = Group::where('slug', 'communications')->firstOrFail();

    expect($communications->has_announcements)->toBeTrue()
        ->and($editor->canActAs(Role::NewsEditor, $communications))->toBeTrue()
        ->and($editor->canPostNews())->toBeTrue();
});

it('excludes the departed Persona from the Directory and My-Groups sources', function () {
    $departed = Member::where('email', 'sven.larsson@dmv.test')
        ->with('memberships')
        ->firstOrFail();

    expect($departed->category)->toBe(Category::Resigned);

    // Directory keys on DMV-wide Category — the departed Persona is not listed.
    expect(Member::inDirectory()->whereKey($departed->id)->exists())->toBeFalse();

    // My-Groups keys on per-Group standing — no Full/on-leave membership qualifies,
    // and the root-as-Full enrolment exempts departed Members, so nothing shows.
    $qualifying = $departed->memberships->filter(fn (GroupMember $m) => in_array(
        $m->status,
        [MembershipStatus::Full, MembershipStatus::Loa],
        true,
    ));
    expect($qualifying)->toBeEmpty();
});

it('gives the multi-group no-office Persona Full standing in several programs', function () {
    $member = Member::where('email', PersonaCatalogue::MEMBER_EMAIL)
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    $programs = $member->memberships
        ->filter(fn (GroupMember $m) => $m->group->kind === Kind::Program && $m->status === MembershipStatus::Full);

    expect($programs->count())->toBeGreaterThanOrEqual(2);
    $member->memberships->each(fn (GroupMember $m) => expect($m->roles)->toBeEmpty());
});

it('demotes test@example.com to an ordinary member with no authority', function () {
    $this->seed(DatabaseSeeder::class);

    $member = Member::where('email', 'test@example.com')
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    expect($member->super_tier)->toBeFalse()
        ->and($member->isAllDmv())->toBeFalse()
        ->and($member->canPostNews())->toBeFalse()
        ->and($member->hasMemberAdminAuthority())->toBeFalse();

    // Only the one root-DMV membership every Member gets, carrying no roles.
    expect($member->memberships)->toHaveCount(1)
        ->and($member->memberships->first()->group->slug)->toBe(DemoSeeder::ROOT)
        ->and($member->memberships->first()->roles)->toBeEmpty();
});

it('allowlists exactly the catalogued Personas', function () {
    expect(PersonaCatalogue::has('nadia.haddad@dmv.test'))->toBeTrue()
        ->and(PersonaCatalogue::has('test@example.com'))->toBeFalse()
        ->and(PersonaCatalogue::emails())->toContain(PersonaCatalogue::CHAIR_EMAIL);
});

/*
 * Scheduling goes live (#364, PRD #352, ADR-0021). The demo org gets one published
 * Schedule on a scheduling-capable Group so a board pitch can click through both the
 * Agenda and the month grid. It lives on Docents — the only demo Group carrying a
 * Scheduler Persona (so the drop-a-Shift cancellation email has a real recipient) and
 * a kinded Group (Reception is deliberately kind-less). These assert the observable
 * shape the two views read, not the exact curated Shifts, so the suite survives the
 * demo month changing.
 */

/** The Docents Schedule named for the current month, as the demo seed names it. */
function docentsCurrentMonth(): Schedule
{
    return Schedule::where('group_id', Group::where('slug', DemoSeeder::PROGRAM)->value('id'))
        ->where('name', CarbonImmutable::instance(now())->format('F Y'))
        ->firstOrFail();
}

it('seeds one published Schedule on the Docents Group covering the current month', function () {
    $schedule = docentsCurrentMonth();

    expect($schedule->state)->toBe(ScheduleState::Published)
        ->and($schedule->isCurrent(now()))->toBeTrue()
        ->and($schedule->starts_on->lessThanOrEqualTo(now()))->toBeTrue()
        ->and($schedule->ends_on->greaterThanOrEqualTo(now()))->toBeTrue();
});

it('seeds an empty next-month draft Schedule on Docents, so a Scheduler has one to publish', function () {
    // The Create a Schedule walkthrough (#530) shoots a draft's Publish and New shift controls.
    // A Member never sees a draft, so the member walkthroughs read as before.
    $docents = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();
    $next = CarbonImmutable::instance(now())->startOfMonth()->addMonth();

    $draft = Schedule::where('group_id', $docents->id)->where('state', ScheduleState::Draft)->sole();

    expect($draft->name)->toBe($next->format('F Y'))
        ->and($draft->starts_on->toDateString())->toBe($next->toDateString())
        ->and($draft->ends_on->toDateString())->toBe($next->endOfMonth()->toDateString())
        ->and($draft->shifts()->count())->toBe(0);
});

it('seeds the real Docents roster: five one-hour, one-Docent tours from 11:00 every day of the month', function () {
    $schedule = docentsCurrentMonth()->load('shifts.kind');
    $orgTimezone = config('app.org_timezone');

    // Every Shift sits inside its Schedule's range (ADR-0021 §2), for the Docents only.
    $schedule->shifts->each(function (Shift $shift) use ($schedule) {
        expect($schedule->coversInterval($shift->starts_at, $shift->ends_at))->toBeTrue()
            ->and($shift->audience)->toBe(ShiftAudience::Group);
    });

    $daily = $schedule->shifts->filter(fn (Shift $s) => $s->kind->name !== 'Group Tour');

    // Each day of the month carries the same five tours: 11:00 to 15:00 on the hour,
    // one hour long, one seat, the label alternating Highlights and Gallery by hour.
    $byDay = $daily->groupBy(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString());
    expect($byDay)->toHaveCount(CarbonImmutable::instance(now())->daysInMonth);

    $byDay->each(function ($tours) use ($orgTimezone) {
        $shape = $tours->sortBy('starts_at')->map(fn (Shift $s) => [
            $s->starts_at->copy()->setTimezone($orgTimezone)->format('H:i'),
            (int) $s->starts_at->diffInMinutes($s->ends_at),
            $s->capacity,
            $s->kind->name,
        ])->values()->all();

        expect($shape)->toBe([
            ['11:00', 60, 1, 'Museum Highlights'],
            ['12:00', 60, 1, 'Gallery/Theme'],
            ['13:00', 60, 1, 'Museum Highlights'],
            ['14:00', 60, 1, 'Gallery/Theme'],
            ['15:00', 60, 1, 'Museum Highlights'],
        ]);
    });
});

it('adds a few Group Tours, the only Docents Shifts with more than one seat', function () {
    $groupTours = docentsCurrentMonth()->shifts()->whereRelation('kind', 'name', 'Group Tour')->get();

    expect($groupTours->count())->toBeGreaterThanOrEqual(3)
        ->and($groupTours->contains(fn (Shift $s) => $s->capacity > 1))->toBeTrue()
        ->and(docentsCurrentMonth()->shifts()->where('capacity', '>', 1)
            ->whereRelation('kind', 'name', '!=', 'Group Tour')->exists())->toBeFalse();
});

it('fills the Docents month the way a real one fills: worked tours full, upcoming ones part-taken', function () {
    $shifts = Shift::whereRelation('schedule', 'group_id', Group::where('slug', DemoSeeder::PROGRAM)->value('id'))
        ->withCount('signUps')
        ->get();

    // Every tour that has ended was worked, so it is full …
    $ended = $shifts->filter(fn (Shift $s) => $s->ends_at->isPast());
    expect($ended)->not->toBeEmpty()
        ->and($ended->every(fn (Shift $s) => $s->sign_ups_count === $s->capacity))->toBeTrue();

    // … and the month still ahead is part-taken, neither empty nor sold out.
    $ahead = docentsCurrentMonth()->shifts()->withCount('signUps')->get()
        ->filter(fn (Shift $s) => $s->starts_at->isFuture() && $s->capacity === 1);

    if ($ahead->count() >= 20) {
        $taken = $ahead->where('sign_ups_count', 1)->count() / $ahead->count();
        expect($taken)->toBeGreaterThan(0.3)->toBeLessThan(0.85);
    }
});

it('signs out the worked Docents tours, leaving only the last two days still owed a number', function () {
    $signUps = SignUp::whereRelation('shift.schedule', 'group_id', Group::where('slug', DemoSeeder::PROGRAM)->value('id'))
        ->with('shift')
        ->get()
        ->filter(fn (SignUp $s) => $s->shift->ends_at->isPast());

    $older = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->lessThan(now()->subDays(2)));
    $recent = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->greaterThanOrEqualTo(now()->subDays(2)));

    expect($older->every(fn (SignUp $s) => $s->visitor_count !== null && $s->extra_interaction_count !== null))->toBeTrue()
        ->and($recent->every(fn (SignUp $s) => $s->visitor_count === null))->toBeTrue();
});

it('drops the separate Recent shifts Schedule on Docents, whose three-hour Shifts Docents never work', function () {
    expect(Schedule::where('group_id', Group::where('slug', DemoSeeder::PROGRAM)->value('id'))
        ->where('name', DemoSeeder::RECENT_SCHEDULE_NAME)->exists())->toBeFalse();
});

/** The Visitor Guides Schedule named for the current month, as the demo seed names it. */
function visitorGuidesCurrentMonth(): Schedule
{
    return Schedule::where('group_id', Group::where('slug', DemoSeeder::VISITOR_GUIDES)->value('id'))
        ->where('name', CarbonImmutable::instance(now())->format('F Y'))
        ->firstOrFail();
}

it('seeds the Visitor Guides desk roster: a two-seat Desk and a one-seat Shadow every hour from 10:00 to 15:00', function () {
    $schedule = visitorGuidesCurrentMonth()->load('shifts.kind');
    $orgTimezone = config('app.org_timezone');
    $month = CarbonImmutable::instance(now())->startOfMonth();

    expect($schedule->state)->toBe(ScheduleState::Published);

    $byDay = $schedule->shifts->groupBy(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString());

    // The desk closes on Mondays outside summer, as legacy runs it.
    $summer = in_array($month->month, [7, 8], true);
    $openDays = collect(range(0, $month->daysInMonth - 1))
        ->map(fn (int $day) => $month->addDays($day))
        ->filter(fn (CarbonImmutable $date) => $summer || ! $date->isMonday());
    expect($byDay->keys()->sort()->values()->all())
        ->toBe($openDays->map->toDateString()->values()->all());

    $expected = [];
    foreach (['10:00', '11:00', '12:00', '13:00', '14:00', '15:00'] as $time) {
        $expected[] = [$time, 60, 2, 'Desk'];
        $expected[] = [$time, 60, 1, 'Shadow'];
    }

    $byDay->each(function ($shifts) use ($orgTimezone, $expected) {
        $shape = $shifts->sortBy([['starts_at', 'asc'], ['capacity', 'desc']])->map(fn (Shift $s) => [
            $s->starts_at->copy()->setTimezone($orgTimezone)->format('H:i'),
            (int) $s->starts_at->diffInMinutes($s->ends_at),
            $s->capacity,
            $s->kind->name,
        ])->values()->all();

        expect($shape)->toBe($expected);
    });
});

it('watches the Visitor Guides Desk kind for the empty-desk alert, and only that kind', function () {
    $kinds = ShiftKind::where('group_id', Group::where('slug', DemoSeeder::VISITOR_GUIDES)->value('id'))
        ->pluck('alert_when_empty', 'name')
        ->all();

    expect($kinds)->toBe(['Desk' => true, 'Shadow' => false]);
});

it('fills the Visitor Guides desk part-way, with some upcoming Desk shifts left empty for the alert', function () {
    $group = Group::where('slug', DemoSeeder::VISITOR_GUIDES)->firstOrFail();
    $desk = Shift::whereRelation('schedule', 'group_id', $group->id)
        ->whereRelation('kind', 'name', 'Desk')
        ->withCount('signUps')
        ->get();

    // Legacy summer months filled about three Desk seats in four.
    $filled = $desk->sum('sign_ups_count') / $desk->sum('capacity');
    expect($filled)->toBeGreaterThan(0.5)->toBeLessThan(0.9);

    // An upcoming Desk shift nobody has taken is what the empty-desk alert reports.
    $ahead = $desk->filter(fn (Shift $s) => $s->starts_at->isFuture());
    if ($ahead->count() >= 20) {
        expect($ahead->contains(fn (Shift $s) => $s->sign_ups_count === 0))->toBeTrue();
    }
});

it('signs out the worked Visitor Guides desk hours, leaving only the last two days still owed a number', function () {
    $signUps = SignUp::whereRelation('shift.schedule', 'group_id', Group::where('slug', DemoSeeder::VISITOR_GUIDES)->value('id'))
        ->with('shift')
        ->get()
        ->filter(fn (SignUp $s) => $s->shift->ends_at->isPast());

    $older = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->lessThan(now()->subDays(2)));
    $recent = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->greaterThanOrEqualTo(now()->subDays(2)));

    expect($older)->not->toBeEmpty()
        ->and($older->every(fn (SignUp $s) => $s->visitor_count >= 10 && $s->visitor_count <= 70))->toBeTrue()
        ->and($older->every(fn (SignUp $s) => $s->extra_interaction_count === null))->toBeTrue()
        ->and($recent->every(fn (SignUp $s) => $s->visitor_count === null))->toBeTrue();
});

it('drops the separate Recent shifts Schedule on Visitor Guides, who work one-hour desk shifts', function () {
    expect(Schedule::where('group_id', Group::where('slug', DemoSeeder::VISITOR_GUIDES)->value('id'))
        ->where('name', DemoSeeder::RECENT_SCHEDULE_NAME)->exists())->toBeFalse();
});

/** The Guides du ROM Schedule named for the current month, as the demo seed names it. */
function guidesDuRomCurrentMonth(): Schedule
{
    return Schedule::where('group_id', Group::where('slug', DemoSeeder::GUIDES_DU_ROM)->value('id'))
        ->where('name', CarbonImmutable::instance(now())->format('F Y'))
        ->firstOrFail();
}

it('seeds the Guides du ROM roster: one 14:00 tour a day, every day but Monday, plus a monthly group tour', function () {
    $schedule = guidesDuRomCurrentMonth()->load('shifts.kind');
    $orgTimezone = config('app.org_timezone');
    $month = CarbonImmutable::instance(now())->startOfMonth();

    expect($schedule->state)->toBe(ScheduleState::Published);

    $daily = $schedule->shifts->where('kind.name', 'Le choix du guide');
    $openDays = collect(range(0, $month->daysInMonth - 1))
        ->map(fn (int $day) => $month->addDays($day))
        ->reject(fn (CarbonImmutable $date) => $date->isMonday())
        ->map->toDateString()
        ->values()
        ->all();

    expect($daily->map(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString())->sort()->values()->all())
        ->toBe($openDays);

    $daily->each(fn (Shift $s) => expect([
        $s->starts_at->copy()->setTimezone($orgTimezone)->format('H:i'),
        (int) $s->starts_at->diffInMinutes($s->ends_at),
        $s->capacity,
    ])->toBe(['14:00', 60, 1]));

    // One free group tour a month, on a Saturday at 11:30, one guide.
    $groupTours = $schedule->shifts->where('kind.name', 'Visite de groupe');
    expect($groupTours)->toHaveCount(1);
    $groupTour = $groupTours->first()->starts_at->copy()->setTimezone($orgTimezone);
    expect($groupTour->isSaturday())->toBeTrue()
        ->and($groupTour->format('H:i'))->toBe('11:30')
        ->and($groupTours->first()->capacity)->toBe(1);
});

it('fills the Guides du ROM month: worked tours full, most upcoming tours already taken', function () {
    $shifts = Shift::whereRelation('schedule', 'group_id', Group::where('slug', DemoSeeder::GUIDES_DU_ROM)->value('id'))
        ->withCount('signUps')
        ->get();

    $ended = $shifts->filter(fn (Shift $s) => $s->ends_at->isPast());
    expect($ended)->not->toBeEmpty()
        ->and($ended->every(fn (Shift $s) => $s->sign_ups_count === 1))->toBeTrue();

    $ahead = $shifts->filter(fn (Shift $s) => $s->starts_at->isFuture());
    if ($ahead->count() >= 10) {
        $taken = $ahead->where('sign_ups_count', 1)->count() / $ahead->count();
        expect($taken)->toBeGreaterThan(0.7);
    }
});

it('drops the separate Recent shifts Schedule on Guides du ROM, who give one-hour tours', function () {
    expect(Schedule::where('group_id', Group::where('slug', DemoSeeder::GUIDES_DU_ROM)->value('id'))
        ->where('name', DemoSeeder::RECENT_SCHEDULE_NAME)->exists())->toBeFalse();
});

it('seeds the Reception roster: three-hour shifts from 09:30 and 12:30 Tuesday to Friday, mornings on the weekend', function () {
    $reception = Group::where('slug', DemoSeeder::RECEPTION)->firstOrFail();
    $schedule = Schedule::where('group_id', $reception->id)
        ->where('name', CarbonImmutable::instance(now())->format('F Y'))
        ->firstOrFail();
    $orgTimezone = config('app.org_timezone');
    $month = CarbonImmutable::instance(now())->startOfMonth();

    expect($schedule->state)->toBe(ScheduleState::Published);

    $expected = [];
    for ($day = 0; $day < $month->daysInMonth; $day++) {
        $date = $month->addDays($day);
        $times = match (true) {
            $date->isMonday() => [],
            $date->isWeekend() => ['09:30'],
            default => ['09:30', '12:30'],
        };
        foreach ($times as $time) {
            $expected[] = [$date->toDateString().' '.$time, 180, 1, null];
        }
    }

    $shape = $schedule->shifts()->orderBy('starts_at')->get()->map(fn (Shift $s) => [
        $s->starts_at->copy()->setTimezone($orgTimezone)->format('Y-m-d H:i'),
        (int) $s->starts_at->diffInMinutes($s->ends_at),
        $s->capacity,
        $s->shift_kind_id,
    ])->all();

    expect($shape)->toBe($expected);
});

it('fills Reception near half, with no visitor count on any seat', function () {
    $reception = Group::where('slug', DemoSeeder::RECEPTION)->firstOrFail();
    $shifts = Shift::whereRelation('schedule', 'group_id', $reception->id)->withCount('signUps')->get();

    $taken = $shifts->sum('sign_ups_count') / $shifts->count();
    expect($taken)->toBeGreaterThan(0.3)->toBeLessThan(0.7)
        ->and(SignUp::whereRelation('shift.schedule', 'group_id', $reception->id)->whereNotNull('visitor_count')->exists())->toBeFalse();
});

it('seeds the Wayfinders month: a five-seat Wayfinding shift every hour from 10:00 to 17:00', function () {
    $wayfinders = Group::where('slug', DemoSeeder::VISITOR_WAYFINDERS)->firstOrFail();
    $month = CarbonImmutable::instance(now())->startOfMonth();
    $schedule = Schedule::where('group_id', $wayfinders->id)
        ->where('name', 'Wayfinding - '.$month->format('F Y'))
        ->with('shifts.kind')
        ->firstOrFail();
    $orgTimezone = config('app.org_timezone');

    expect($schedule->state)->toBe(ScheduleState::Published)
        ->and($schedule->starts_on->toDateString())->toBe($month->toDateString())
        ->and($schedule->ends_on->toDateString())->toBe($month->endOfMonth()->toDateString());

    $byDay = $schedule->shifts->groupBy(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString());
    $summer = in_array($month->month, [7, 8], true);
    $openDays = collect(range(0, $month->daysInMonth - 1))
        ->map(fn (int $day) => $month->addDays($day))
        ->filter(fn (CarbonImmutable $date) => $summer || ! $date->isMonday())
        ->map->toDateString()
        ->values()
        ->all();
    expect($byDay->keys()->sort()->values()->all())->toBe($openDays);

    $byDay->each(fn ($shifts) => expect($shifts->sortBy('starts_at')->map(fn (Shift $s) => [
        $s->starts_at->copy()->setTimezone($orgTimezone)->format('H:i'),
        (int) $s->starts_at->diffInMinutes($s->ends_at),
        $s->capacity,
        $s->kind->name,
    ])->values()->all())->toBe(collect(range(10, 16))->map(fn (int $h) => [sprintf('%02d:00', $h), 60, 5, 'Wayfinding'])->all()));
});

it('seeds the Wayfinders evening events as one-day Schedules staffed by station', function () {
    $wayfinders = Group::where('slug', DemoSeeder::VISITOR_WAYFINDERS)->firstOrFail();
    $month = CarbonImmutable::instance(now())->startOfMonth();

    $events = Schedule::where('group_id', $wayfinders->id)
        ->where('name', 'not like', 'Wayfinding - '.$month->format('F Y'))
        ->where('name', 'not like', 'Wayfinding - '.$month->subMonth()->format('F Y'))
        ->with('shifts.kind')
        ->get();

    // TTNF and ROM After Dark run both months; Members Evening only this month.
    expect($events)->toHaveCount(5)
        ->and($events->pluck('name')->filter(fn (string $n) => str_starts_with($n, 'Wayfinding - TTNF'))->count())->toBe(2)
        ->and($events->pluck('name')->filter(fn (string $n) => str_starts_with($n, 'Members Evening'))->count())->toBe(1);

    $events->each(function (Schedule $event) {
        // One day, labelled by station, never the daytime Wayfinding label.
        expect($event->starts_on->toDateString())->toBe($event->ends_on->toDateString())
            ->and($event->shifts)->not->toBeEmpty()
            ->and($event->shifts->contains(fn (Shift $s) => $s->kind->name === 'Wayfinding'))->toBeFalse()
            ->and($event->shifts->every(fn (Shift $s) => $event->coversInterval($s->starts_at, $s->ends_at)))->toBeTrue();
    });
});

it('fills the Wayfinders daytime roster thinly and the events well, as legacy does', function () {
    $wayfinders = Group::where('slug', DemoSeeder::VISITOR_WAYFINDERS)->firstOrFail();
    $shifts = Shift::whereRelation('schedule', 'group_id', $wayfinders->id)->with('kind')->withCount('signUps')->get();

    [$daytime, $events] = $shifts->partition(fn (Shift $s) => $s->kind->name === 'Wayfinding');

    $daytimeFill = $daytime->sum('sign_ups_count') / $daytime->sum('capacity');
    $eventFill = $events->sum('sign_ups_count') / $events->sum('capacity');

    expect($daytimeFill)->toBeGreaterThan(0.05)->toBeLessThan(0.3)
        ->and($eventFill)->toBeGreaterThan(0.5)->toBeLessThan(0.95)
        ->and(Schedule::where('group_id', $wayfinders->id)->where('name', DemoSeeder::RECENT_SCHEDULE_NAME)->exists())->toBeFalse();
});

it('seeds an active ShiftKind vocabulary for the Group and labels its Shifts', function () {
    $docents = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();

    $kinds = ShiftKind::where('group_id', $docents->id)->get();

    // A small vocabulary, all offered (active) to new Shifts.
    expect($kinds->count())->toBeGreaterThanOrEqual(2)
        ->and($kinds->every(fn (ShiftKind $k) => $k->active))->toBeTrue();

    // The kinds label real Shifts on the Group's Schedule — the Agenda shows a kind,
    // not a bare time.
    $shifts = Shift::whereRelation('schedule', 'group_id', $docents->id)->get();
    expect($shifts->contains(fn (Shift $s) => $s->shift_kind_id !== null))->toBeTrue();

    $kindIds = $kinds->pluck('id');
    $shifts->whereNotNull('shift_kind_id')->each(
        fn (Shift $s) => expect($kindIds->contains($s->shift_kind_id))->toBeTrue(),
    );
});

it('places some Sign-ups without exceeding capacity and leaves a seat free to take', function () {
    $docents = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();

    $shifts = Shift::whereRelation('schedule', 'group_id', $docents->id)
        ->withCount('signUps')
        ->get();

    // Some seats are already taken, so the roster does not read as an empty month …
    expect($shifts->sum('sign_ups_count'))->toBeGreaterThan(0)
        // … no Shift is over-subscribed (the seeder respects capacity directly) …
        ->and($shifts->every(fn (Shift $s) => $s->sign_ups_count <= $s->capacity))->toBeTrue()
        // … and at least one Shift still has room, so a walkthrough can take a seat.
        ->and($shifts->contains(fn (Shift $s) => $s->sign_ups_count < $s->capacity))->toBeTrue();

    // Every seated Member is a real Docents Member — a legitimate group-audience seat.
    $rosterIds = GroupMember::where('group_id', $docents->id)->pluck('member_id');
    SignUp::whereRelation('shift.schedule', 'group_id', $docents->id)->get()->each(
        fn (SignUp $signUp) => expect($rosterIds->contains($signUp->member_id))->toBeTrue(),
    );
});

it('turns scheduling on for the demo programs but off for the booking-only Groups', function () {
    // Scheduling goes live for the programs whose shape is shift work (#364) …
    expect(Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail()->has_scheduling)->toBeTrue()
        ->and(Group::where('slug', 'reception')->firstOrFail()->has_scheduling)->toBeTrue()
        // … and stays off for ROMBus and Outreach, whose only shape is group booking —
        // the capability deferred out of the first pass (ADR-0021).
        ->and(Group::where('slug', 'rombus')->firstOrFail()->has_scheduling)->toBeFalse()
        ->and(Group::where('slug', 'outreach')->firstOrFail()->has_scheduling)->toBeFalse();
});

it('turns Reminders on with 3 lead days for the five Reminder Groups, off elsewhere', function () {
    // The five Groups that run Reminders today (ADR-0024 §7), each at the standard 3 lead days.
    foreach (['docents', 'guides-du-rom', 'visitor-wayfinders', 'visitor-guides', 'reception'] as $slug) {
        $group = Group::where('slug', $slug)->firstOrFail();
        expect($group->reminders_enabled)->toBeTrue()
            ->and($group->reminder_lead_days)->toBe(3);
    }

    // A scheduling Group not on the list keeps Reminders off (the opt-in default).
    expect(Group::where('slug', 'romwalks')->firstOrFail()->reminders_enabled)->toBeFalse();
});

it('turns self-serve shifts on with 45-minute units for Gallery Interpreters, off elsewhere', function () {
    // Gallery Interpreters is the one self-serve Group (ADR-0026 §1), at the 45-minute unit
    // every GI knows (§2).
    $gi = Group::where('slug', 'gallery-interpreters')->firstOrFail();
    expect($gi->self_serve_shifts)->toBeTrue()
        ->and($gi->self_serve_unit_minutes)->toBe(45);

    // Every other scheduling Group keeps self-serve off (the opt-in default).
    expect(Group::where('slug', 'docents')->firstOrFail()->self_serve_shifts)->toBeFalse()
        ->and(Group::where('slug', 'visitor-guides')->firstOrFail()->self_serve_shifts)->toBeFalse();
});

it('seeds the walks-to-hours multipliers as decimals — ROMWalks 2, Gallery Interpreters 1.333, every other Group 1', function () {
    // ROMWalks counts each walk as two hours; Gallery Interpreters credit one hour per
    // 45-minute unit — 1.333 (ADR-0022 §7, ADR-0026 §7). Every other Group keeps the
    // org-wide default. The multiplier round-trips as a three-place decimal.
    expect(Group::where('slug', 'romwalks')->firstOrFail()->hours_multiplier)->toBe('2.000')
        ->and(Group::where('slug', 'gallery-interpreters')->firstOrFail()->hours_multiplier)->toBe('1.333')
        ->and(Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail()->hours_multiplier)->toBe('1.000')
        ->and(Group::where('slug', 'reception')->firstOrFail()->hours_multiplier)->toBe('1.000');
});

/*
 * The after-the-shift visitor record goes live in the demo org (#474, PRD #443, ADR-0023).
 * The feature shipped correct but invisible on staging because nothing turned the Group
 * switches on or wrote a single count — #428's failure again. These assert a fresh seed
 * exercises it end to end: the switches, the per-seat numbers, GDR's origins, the Hours-tab
 * interactions, and the report they all feed.
 */

it('turns the four visitor switches on per Group, with Reception collecting nothing', function () {
    $gdr = Group::where('slug', 'guides-du-rom')->firstOrFail();
    $docents = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();
    $reception = Group::where('slug', DemoSeeder::RECEPTION)->firstOrFail();

    // GDR is the one Group carrying all four, and the only one anywhere with provenance.
    expect($gdr->collects_visitor_count)->toBeTrue()
        ->and($gdr->collects_extra_interactions)->toBeTrue()
        ->and($gdr->collects_visitor_provenance)->toBeTrue()
        ->and($gdr->visitor_figures_await_booking)->toBeTrue()
        ->and(Group::where('collects_visitor_provenance', true)->count())->toBe(1);

    // A tour-leading Group collects the split; Reception, the tenth scheduling Group,
    // collects nothing at all (ADR-0023 §5) so it renders no sign-out panel.
    expect($docents->collects_visitor_count)->toBeTrue()
        ->and($docents->collects_extra_interactions)->toBeTrue()
        ->and($reception->collects_visitor_count)->toBeFalse()
        ->and($reception->collects_extra_interactions)->toBeFalse()
        ->and($reception->collects_visitor_provenance)->toBeFalse()
        ->and($reception->visitor_figures_await_booking)->toBeFalse();

    // Each of the four switches is on for at least one Group on a fresh seed.
    foreach (['collects_visitor_count', 'collects_extra_interactions', 'collects_visitor_provenance', 'visitor_figures_await_booking'] as $switch) {
        expect(Group::where($switch, true)->exists())->toBeTrue();
    }
});

it('collects the split only on tour-leading Groups, never on desk or gallery Groups', function () {
    // Tour-leading Groups carry the second box (ADR-0023 §2) …
    foreach (['docents', 'guides-du-rom', 'romwalks'] as $slug) {
        expect(Group::where('slug', $slug)->firstOrFail()->collects_extra_interactions)->toBeTrue();
    }

    // … while a desk or gallery Group's visitor count already is an interaction count.
    foreach (['visitor-guides', 'visitor-wayfinders', 'gallery-interpreters'] as $slug) {
        $group = Group::where('slug', $slug)->firstOrFail();
        expect($group->collects_visitor_count)->toBeTrue()
            ->and($group->collects_extra_interactions)->toBeFalse();
    }
});

it('records visitor counts on past Sign-ups for collecting Groups, including recorded zeroes', function () {
    // Every collecting Group's recent Schedule carries ended Shifts whose seats were signed
    // out — a real recorded count, and a few a deliberate recorded zero, distinct from null.
    $collecting = Group::where('collects_visitor_count', true)->pluck('id');

    $recorded = SignUp::query()
        ->whereNotNull('visitor_count')
        ->whereHas('shift.schedule', fn ($query) => $query->whereIn('group_id', $collecting))
        ->get();

    expect($recorded)->not->toBeEmpty()
        ->and($recorded->contains(fn (SignUp $s) => $s->visitor_count > 0))->toBeTrue()
        ->and($recorded->contains(fn (SignUp $s) => $s->visitor_count === 0))->toBeTrue();
});

it('leaves a persona an outstanding past Shift — a null count inside the window', function () {
    // The outstanding-shifts panel reads a null visitor_count on a Shift ended within the
    // window (ADR-0023 §5). A demo persona must hold one, or a walkthrough sees an empty panel.
    $personaIds = Member::whereIn('email', PersonaCatalogue::emails())->pluck('id');

    $outstanding = SignUp::query()
        ->whereIn('member_id', $personaIds)
        ->whereNull('visitor_count')
        ->whereHas('shift', fn ($query) => $query
            ->where('ends_at', '<', now())
            ->where('ends_at', '>=', now()->subDays(SignUp::OUTSTANDING_WINDOW_DAYS)))
        ->exists();

    expect($outstanding)->toBeTrue();
});

it('seats the Member Persona on the outstanding Docents Shift, so her My sign-ups panel has a number to file', function () {
    // The help screenshots and a Member walkthrough run as the Member Persona (#529). Her
    // Scheduling tab must show a past Docents Shift still owed a count, and the sign-out form
    // on it, so the seed hands her the first null seat rather than whoever has the lowest id.
    $member = Member::where('email', PersonaCatalogue::MEMBER_EMAIL)->firstOrFail();
    $docents = Group::where('slug', 'docents')->firstOrFail();

    $outstanding = SignUp::query()
        ->where('member_id', $member->id)
        ->whereNull('visitor_count')
        ->whereHas('shift.schedule', fn ($query) => $query->where('group_id', $docents->id))
        ->whereHas('shift', fn ($query) => $query
            ->where('ends_at', '<', now())
            ->where('ends_at', '>=', now()->subDays(SignUp::OUTSTANDING_WINDOW_DAYS)))
        ->exists();

    expect($outstanding)->toBeTrue();
});

it('seats the Member Persona on the last Shift of the Docents month, so she has a seat to drop', function () {
    // The Cancel-a-sign-up walkthrough needs the Drop button on a Shift still ahead of her.
    // The month's last tour stays ahead for as long as the month runs.
    $member = Member::where('email', PersonaCatalogue::MEMBER_EMAIL)->firstOrFail();
    $last = docentsCurrentMonth()->shifts()->orderByDesc('starts_at')->firstOrFail();

    expect(SignUp::where('shift_id', $last->id)->where('member_id', $member->id)->exists())->toBeTrue();
});

it('splits every counted GDR Sign-up into five origins that sum to the count', function () {
    $gdr = Group::where('slug', 'guides-du-rom')->firstOrFail();

    $signUps = SignUp::query()
        ->whereNotNull('visitor_count')
        ->whereHas('shift.schedule', fn ($query) => $query->where('group_id', $gdr->id))
        ->get();

    expect($signUps)->not->toBeEmpty();
    $signUps->each(function (SignUp $signUp) {
        $sum = $signUp->visitors_france_europe
            + $signUp->visitors_quebec
            + $signUp->visitors_toronto
            + $signUp->visitors_rest_of_canada
            + $signUp->visitors_other_countries;

        expect($sum)->toBe($signUp->visitor_count);
    });
});

it('carries extra_interactions on the Groups whose only route into the report is the Hours tab', function () {
    // Five of the six Groups in EXTRA_INTERACTION_GROUPS (ADR-0023 §6) — ROM Travel, Hands-on
    // Tours, ROMBus and two Friends committees — appear only on hand-typed extra interactions.
    // The sixth (DMV root) is excluded: its extra_interactions represent its own roll-up entry,
    // not a leaf group's sole report presence.
    foreach (['romtravel', 'dmv-hands-on-tours', 'rombus', 'friends-of-palaeontology-fop', 'friends-of-global-south-asia-fsa'] as $slug) {
        $group = Group::where('slug', $slug)->firstOrFail();
        expect(HoursRecord::where('group_id', $group->id)->where('extra_interactions', '>', 0)->exists())->toBeTrue();
    }

    // ROM Travel runs no scheduling at all, so its extra interactions are its entire presence.
    expect(Group::where('slug', 'romtravel')->firstOrFail()->has_scheduling)->toBeFalse();
});

it('renders a non-empty Summary Visitor Interactions across two fiscal years', function () {
    // The assertion #428 was missing: a fresh seed must not print an empty grid.
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $currentFiscalYear = OrgTime::currentFiscalYear();

    foreach ([$currentFiscalYear - 1, $currentFiscalYear] as $fiscalYear) {
        $stats = VisitorInteractionStatistics::for($root, $fiscalYear);

        expect($stats->groups)->not->toBeEmpty()
            ->and(array_sum(array_column($stats->groups, 'ytd')))->toBeGreaterThan(0);
    }

    $current = VisitorInteractionStatistics::for($root, $currentFiscalYear);

    // A Group whose figures wait on the group-booking work flies the incomplete marker …
    expect(collect($current->groups)->contains(fn (array $g) => $g['incomplete'] === true))->toBeTrue()
        // … and ROM Travel, which never schedules, still reaches the report.
        ->and(collect($current->groups)->pluck('name'))->toContain('ROMTravel');
});

it('feeds the Detailed Committee Statistics fourth row with visitor numbers', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();

    $stats = CommitteeHoursStatistics::for($root, OrgTime::currentFiscalYear());

    // The fourth grain beside shifts, meetings and extra hours carries real numbers.
    expect($stats->org['ytd']['interactions'])->toBeGreaterThan(0);
});

it('gives Gallery Interpreters a handling collection and no other Group any Objects (#584)', function () {
    $gi = Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->firstOrFail();

    // Gallery Interpreters carries about 40 named, active Objects in authored order.
    expect($gi->objects()->count())->toBeGreaterThanOrEqual(35)
        ->and($gi->objects()->where('active', true)->count())->toBe($gi->objects()->count());

    // No other Group has any Object.
    expect(HandlingObject::where('group_id', '!=', $gi->id)->count())->toBe(0);
});

/** The Gallery Interpreters Schedule named for the current month, as the demo seed names it. */
function galleryInterpretersCurrentMonth(): Schedule
{
    return Schedule::where('group_id', Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->value('id'))
        ->where('name', CarbonImmutable::instance(now())->format('F Y'))
        ->firstOrFail();
}

/** Every Gallery Interpreters Sign-up, its Shift and kind and reserved Objects eager-loaded. */
function galleryInterpreterSignUps(): Collection
{
    return SignUp::whereRelation('shift.schedule', 'group_id', Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->value('id'))
        ->with(['shift.kind', 'objects'])
        ->get();
}

it('seeds last month on Gallery Interpreters too, worked and signed out throughout', function () {
    $lastMonth = CarbonImmutable::instance(now())->startOfMonth()->subMonth();
    $schedule = Schedule::where('group_id', Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->value('id'))
        ->where('name', $lastMonth->format('F Y'))
        ->with('shifts.signUps')
        ->firstOrFail();

    expect($schedule->state)->toBe(ScheduleState::Published)
        ->and($schedule->shifts->count())->toBeGreaterThan(80)
        // Every seat on a month gone by is taken, signed out, and holds its Objects.
        ->and($schedule->shifts->every(fn (Shift $s) => $s->signUps->count() === $s->capacity))->toBeTrue()
        ->and($schedule->shifts->every(fn (Shift $s) => $s->signUps->every(fn (SignUp $u) => $u->visitor_count !== null)))->toBeTrue();

    $reserved = SignUp::whereRelation('shift', 'schedule_id', $schedule->id)->withCount('objects')->get();
    expect($reserved->every(fn (SignUp $u) => $u->objects_count === 3))->toBeTrue();
});

it('drops the separate Recent shifts Schedule on Gallery Interpreters, who author their own shifts', function () {
    expect(Schedule::where('group_id', Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->value('id'))
        ->where('name', DemoSeeder::RECENT_SCHEDULE_NAME)->exists())->toBeFalse();
});

it('seeds a Gallery Interpreters month of self-serve shifts: about 4.5 a day, Tue–Sun, at distinct hourly and half-hourly starts', function () {
    $schedule = galleryInterpretersCurrentMonth()->load('shifts.kind');
    $orgTimezone = config('app.org_timezone');
    $month = CarbonImmutable::instance(now())->startOfMonth();

    expect($schedule->state)->toBe(ScheduleState::Published);

    // The regular gallery shifts — the off-site event station is measured on its own below.
    $regular = $schedule->shifts->filter(fn (Shift $s) => ! $s->kind->off_site);

    // Tuesday to Sunday only: no gallery shift lands on a Monday.
    $byDay = $regular->groupBy(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString());
    expect($byDay->keys()->every(fn (string $date) => ! CarbonImmutable::parse($date)->isMonday()))->toBeTrue();

    // About four and a half a working day.
    $workingDays = collect(range(0, $month->daysInMonth - 1))
        ->map(fn (int $day) => $month->addDays($day))
        ->reject(fn (CarbonImmutable $date) => $date->isMonday())
        ->count();
    expect($regular->count() / $workingDays)->toBeGreaterThan(4.0)->toBeLessThan(5.0);

    // Every shift is self-serve shaped: capacity 1, one Sign-up, a station kind, one to three units.
    $regular->each(function (Shift $s) use ($orgTimezone) {
        $wall = $s->starts_at->copy()->setTimezone($orgTimezone);
        $units = (int) round($s->starts_at->diffInMinutes($s->ends_at) / 45);

        expect($s->capacity)->toBe(1)
            ->and($s->signUps()->count())->toBe(1)
            ->and($units)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3)
            // Starts on the hour or half hour, from 10:00 to 16:00.
            ->and($wall->hour)->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(16)
            ->and(in_array($wall->minute, [0, 30], true))->toBeTrue()
            ->and(in_array($s->kind->name, DemoSeeder::GI_STATIONS, true))->toBeTrue();
    });

    // The busy front five carry more of the month than the quiet four (ADR-0026 §2).
    $byStation = $regular->countBy(fn (Shift $s) => $s->kind->name);
    $busy = collect(array_slice(DemoSeeder::GI_STATIONS, 0, 5))->sum(fn (string $name) => $byStation[$name] ?? 0);
    $quiet = collect(array_slice(DemoSeeder::GI_STATIONS, 5))->sum(fn (string $name) => $byStation[$name] ?? 0);
    expect($busy)->toBeGreaterThan($quiet)
        // …but all nine stations are worked at least once.
        ->and($byStation->keys()->sort()->values()->all())->toBe(collect(DemoSeeder::GI_STATIONS)->sort()->values()->all());
});

it('reserves three Objects on every GI Sign-up with none double-booked across overlapping holds', function () {
    $signUps = galleryInterpreterSignUps();

    expect($signUps)->not->toBeEmpty()
        ->and($signUps->every(fn (SignUp $s) => $s->objects->count() === 3))->toBeTrue();

    // The double-booking block the app runs on write: an Object may sit on two Sign-ups only when
    // their Shifts' holds do not overlap ({@see App\Support\Scheduling\ObjectClash}).
    $byObject = [];
    foreach ($signUps as $signUp) {
        foreach ($signUp->objects as $object) {
            $byObject[$object->id][] = $signUp->shift;
        }
    }

    foreach ($byObject as $shifts) {
        foreach ($shifts as $i => $a) {
            foreach (array_slice($shifts, $i + 1) as $b) {
                expect($a->objectHold()->overlaps($b->objectHold()))->toBeFalse();
            }
        }
    }
});

it('puts no GI station on two seats whose times overlap, so the seed clears the station-clash check', function () {
    $signUps = galleryInterpreterSignUps();

    // The station-clash warning the app raises: two Sign-ups on one kind whose Shift intervals
    // overlap ({@see App\Support\Scheduling\ObjectClash::station}). The seed must raise none.
    $byKind = $signUps->groupBy(fn (SignUp $s) => $s->shift->shift_kind_id);

    $byKind->each(function ($seats) {
        $shifts = $seats->pluck('shift')->values();
        foreach ($shifts as $i => $a) {
            foreach ($shifts->slice($i + 1) as $b) {
                $overlap = $a->starts_at->lessThan($b->ends_at) && $b->starts_at->lessThan($a->ends_at);
                expect($overlap)->toBeFalse();
            }
        }
    });
});

it('records the worked GI past days and leaves the most recent shifts outstanding', function () {
    $signUps = galleryInterpreterSignUps()->filter(fn (SignUp $s) => $s->shift->ends_at->isPast());

    $older = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->lessThan(now()->subDays(2)));
    $recent = $signUps->filter(fn (SignUp $s) => $s->shift->ends_at->greaterThanOrEqualTo(now()->subDays(2)));

    // Past days carry the visitor numbers filed at sign-out, about 32 a 45-minute unit.
    expect($older)->not->toBeEmpty()
        ->and($older->every(fn (SignUp $s) => $s->visitor_count > 0))->toBeTrue()
        ->and($older->every(function (SignUp $s) {
            $units = (int) round($s->shift->starts_at->diffInMinutes($s->shift->ends_at) / 45);

            return $s->visitor_count >= $units * 26 && $s->visitor_count <= $units * 38;
        }))->toBeTrue()
        // The recent shifts are still owed a number — the outstanding panel reads them (ADR-0023 §5).
        ->and($recent)->not->toBeEmpty()
        ->and($recent->every(fn (SignUp $s) => $s->visitor_count === null))->toBeTrue();
});

it('seeds one off-site GI event a month over consecutive days, its Objects held a day either side', function () {
    $gi = Group::where('slug', DemoSeeder::GALLERY_INTERPRETERS)->firstOrFail();

    // The off-site station is the one kind flagged off_site (ADR-0026 §4).
    $offSite = ShiftKind::where('group_id', $gi->id)->where('off_site', true)->get();
    expect($offSite)->toHaveCount(1);

    $shifts = Shift::whereRelation('schedule', 'group_id', $gi->id)
        ->where('shift_kind_id', $offSite->first()->id)
        ->with(['kind', 'signUps.objects'])
        ->orderBy('starts_at')
        ->get();

    $orgTimezone = config('app.org_timezone');

    // One event a month, each carrying seats with Objects on them.
    expect($shifts->pluck('schedule_id')->unique())->toHaveCount(2)
        ->and($shifts->every(fn (Shift $s) => $s->signUps->isNotEmpty()))->toBeTrue()
        ->and($shifts->every(fn (Shift $s) => $s->signUps->every(fn (SignUp $u) => $u->objects->isNotEmpty())))->toBeTrue();

    $shifts->groupBy('schedule_id')->each(function ($event) use ($orgTimezone) {
        $days = $event->map(fn (Shift $s) => $s->starts_at->copy()->setTimezone($orgTimezone)->toDateString())->unique()->values();

        expect($days->count())->toBe(DemoSeeder::GI_EVENT_DAYS);

        for ($i = 1; $i < $days->count(); $i++) {
            expect(CarbonImmutable::parse($days[$i - 1])->addDay()->toDateString())->toBe($days[$i]);
        }
    });

    // The off-site kind widens the Object hold beyond the shift — a day before to a day after.
    $shifts->each(function (Shift $s) {
        expect($s->objectHold()->start->lessThan($s->starts_at))->toBeTrue()
            ->and($s->objectHold()->end->greaterThan($s->ends_at))->toBeTrue();
    });
});

it('is idempotent across the scheduling rows — re-seeding heals rather than duplicates', function () {
    $counts = fn () => [
        'schedules' => Schedule::count(),
        'shifts' => Shift::count(),
        'shiftKinds' => ShiftKind::count(),
        'objects' => HandlingObject::count(),
        'signUps' => SignUp::count(),
    ];
    $before = $counts();

    // A published Schedule with Shifts, kinds and Sign-ups exists after the first seed.
    expect($before['schedules'])->toBeGreaterThan(0)
        ->and($before['shifts'])->toBeGreaterThan(0)
        ->and($before['shiftKinds'])->toBeGreaterThan(0)
        ->and($before['objects'])->toBeGreaterThan(0)
        ->and($before['signUps'])->toBeGreaterThan(0);

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});

/*
 * Meetings on the Executive committee (#530). The Record a meeting walkthrough shoots the
 * Meetings tab as the Executive Secretary, so the tab shows real cards with their Edit and
 * Delete controls rather than the empty state.
 */

it('seeds an upcoming and a past meeting on the Executive committee, the past one with minutes', function () {
    $executive = Group::where('slug', 'executive')->firstOrFail();

    $meetings = Meeting::where('group_id', $executive->id)->with('links')->get();
    $upcoming = $meetings->filter(fn (Meeting $meeting) => $meeting->held_at->isFuture());
    $past = $meetings->filter(fn (Meeting $meeting) => $meeting->held_at->isPast());

    expect($executive->has_meetings)->toBeTrue()
        ->and($upcoming)->toHaveCount(1)
        ->and($past)->toHaveCount(1)
        ->and($meetings->every(fn (Meeting $meeting) => $meeting->is_published))->toBeTrue()
        ->and($past->first()->links->pluck('kind')->all())->toContain(MeetingLinkKind::Minutes);
});

it('is idempotent across the meeting rows — re-seeding heals rather than duplicates', function () {
    $counts = fn () => [Meeting::count(), MeetingLink::count()];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});
