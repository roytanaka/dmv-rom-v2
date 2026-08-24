<?php

namespace App\Http\Controllers;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Enums\ShiftAudience;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\MemberResource;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\MeetingLink;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Group detail page (#188, PRD #186) — the "committee shell" every Group runs
 * on, expressed once and reused for every Kind. This slice renders the persistent
 * header (banner + name + parent breadcrumb + lifecycle badge), the in-body section
 * tabs, and the read-only Overview tab. Roster (#189) and Meetings (#190) fill in
 * their own panels on top of this shell.
 *
 * Any logged-in Member may view an active Group's Overview; an archived Group still
 * renders directly (history) but is absent from the navigation lists (its parent's
 * child-Group list excludes it). An unknown slug 404s via slug route-model binding.
 */
class GroupController extends Controller
{
    /**
     * The Group page. The {section} segment selects the active tab; a bare slug
     * lands on Overview. The Group is bound by slug ({@see Group::getRouteKeyName}),
     * so an unknown slug 404s before this runs.
     */
    public function show(Request $request, Group $group, ?string $section = null): Response
    {
        return $this->render($request, $group, $section ?? 'overview', null);
    }

    /**
     * A Schedule permalink (`groups.scheduling.show`, #353) — the Scheduling section
     * opened on one Schedule addressed by id. A dedicated action rather than an extra
     * optional param on {@see show()}, so each route's parameters bind by name and the
     * {schedule} model never spills into the {section} slot. The per-Schedule read is
     * enforced in {@see scheduling()} via the SchedulePolicy.
     */
    public function showSchedule(Request $request, Group $group, Schedule $schedule): Response
    {
        return $this->render($request, $group, 'scheduling', $schedule);
    }

    /**
     * Render the committee shell on the given section, optionally opened on a specific
     * Schedule. Shared by {@see show()} and {@see showSchedule()}.
     */
    private function render(Request $request, Group $group, string $section, ?Schedule $schedule): Response
    {
        // Container page-gate (#293, PRD #289): a Kind::Container Group is a structural
        // section peer, not a destination — no page exists. Unconditional 404 for every
        // viewer, super-tier included: unlike the Private gate below (a confidentiality
        // boundary that exempts members and the super-tier), this is a fact, not an
        // access decision. 404 (not 403) so the navigation and the addressable pages agree.
        if ($group->kind === Kind::Container) {
            abort(404);
        }

        // Resolve the viewer's memberships and roles once, in memory: both the
        // GroupPolicy (canActAs, for the `can` hint) and the MeetingPolicy (for
        // the members-only meetings gate below) traverse them, and strict mode
        // forbids the lazy load in either case.
        $request->user()->loadMissing('memberships.roles');

        // Private page-gate (#270, ADR-0019): keeps a Private Group's existence hidden.
        // 404 (not 403) so the boundary never confirms the Group exists. Parentage
        // grants no content access: a parent officer who is not a member of this child
        // is a non-member here. Public and Group visibility are org-open (navigation
        // tidiness, not confidentiality; only Private is a boundary).
        if ($group->listing_visibility === ListingVisibility::Private
            && ! $request->user()->isAllDmv()
            && $request->user()->membershipIn($group) === null) {
            abort(404);
        }

        // The Meetings list is members-only (MeetingPolicy), unlike the org-open
        // Overview and Roster — a non-member visiting the section is forbidden.
        if ($section === 'meetings') {
            abort_unless($request->user()->can('viewAny', [Meeting::class, $group]), 403);
        }

        // The Scheduling section exists only while the Group runs scheduling — a 404
        // (not 403) so a non-scheduling Group's tab and its addressable URL agree. The
        // section itself is org-open (SchedulePolicy); per-Schedule read is gated below.
        if ($section === 'scheduling') {
            abort_unless($request->user()->can('viewAny', [Schedule::class, $group]), 404);
        }

        $group->load([
            'parent',
            // Only active children are navigable — an archived child still renders
            // when visited directly but never appears in its parent's list.
            'children' => fn ($query) => $query->active()
                ->orderBy('display_order')
                ->orderBy('name'),
            'memberships.member',
            'memberships.roles',
        ]);

        return Inertia::render('groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                // The selected banner key (null falls back to the neutral default
                // on the client); the curated set drives the officer picker.
                'banner_key' => $group->banner_key?->value,
                'archived' => $group->lifecycle_state === LifecycleState::Archived,
                'end_date' => $group->end_date?->toDateString(),
                'parent' => $group->parent ? [
                    'name' => $group->parent->name,
                    'slug' => $group->parent->slug,
                ] : null,
                // Capability flags drive which section tabs render (the base triad
                // plus the muted "soon" stubs for a capability the Group runs). Hours
                // is not among them — it is always-on (ADR-0022 §3), so its tab renders
                // on every Group and needs no flag.
                'capabilities' => [
                    'meetings' => $group->has_meetings,
                    'documents' => $group->has_documents,
                    'scheduling' => $group->has_scheduling,
                    'content' => $group->has_content_catalog,
                ],
            ],
            'section' => $section,
            // UI hints only — the server enforces in the Form Requests. `update`
            // drives the Overview's inline About Us edit and banner picker;
            // `createMeeting` drives the Meetings tab's "New meeting" affordance;
            // `manageRoster` drives the Roster tab's officer CRUD (#192);
            // `createSchedule` drives the Scheduling tab's "New schedule" affordance (#354).
            'can' => [
                'update' => $request->user()->can('update', $group),
                'createMeeting' => $request->user()->can('create', [Meeting::class, $group]),
                'manageRoster' => $request->user()->can('create', [GroupMember::class, $group]),
                'createSchedule' => $request->user()->can('create', [Schedule::class, $group]),
            ],
            // The Roster tab's payload is resolved only when that tab is active —
            // its per-row contact gating eager-loads each member's memberships, work
            // the Overview never needs.
            'roster' => $section === 'roster' ? $this->roster($request, $group) : [],
            // Officer roster CRUD scaffolding (#192), resolved only on the Roster tab.
            // `candidates` (Members not yet in the Group, for the add-member search)
            // and `assignableRoles` (the Group's capability-valid roles) are withheld
            // from a non-officer; `showingPast` reflects the officer-only Resigned
            // reveal so the toggle renders its current state.
            'rosterMeta' => $section === 'roster' ? $this->rosterMeta($request, $group) : [
                'candidates' => [],
                'assignableRoles' => [],
                'showingPast' => false,
            ],
            // The Meetings tab's payload is resolved only when that tab is active
            // and the viewer has cleared the members-only gate above.
            'meetings' => $section === 'meetings' ? $this->meetings($request, $group) : [],
            // The Scheduling tab's payload, resolved only on that tab: the viewer's
            // visible Schedules and which one (if any) opens directly.
            'scheduling' => $section === 'scheduling'
                ? $this->scheduling($request, $group, $schedule)
                : ['schedules' => [], 'open' => null, 'roster' => [], 'shift_kinds' => []],
            'overview' => [
                // About Us — member-authored content, rendered as-authored.
                'description' => $group->description,
                'children' => $group->children
                    ->map(fn (Group $child) => [
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ])
                    ->all(),
                'leadership' => $this->leadership($group),
                'facts' => [
                    'member_count' => $group->memberships
                        ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased])
                        ->count(),
                    'meets' => $group->has_meetings,
                    'time_boxed' => $group->time_boxed,
                    'start_date' => $group->start_date?->toDateString(),
                    'end_date' => $group->end_date?->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Officer edit to the Group's Overview (#191) — inline About Us text and the
     * curated banner selection. Authorization and the field whitelist both live in
     * the Form Request (the GroupPolicy → `can`-prop convention for Groups).
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return back();
    }

    /**
     * The Group's officers, by role — the "leadership at a glance" list. One entry
     * per (role, member), ordered by the spine's role catalogue so Chair leads.
     * Names link through to profiles on the page; departed members are excluded.
     *
     * The org root (a parentless Group) is the exception: it carries no Chair. Its
     * leadership is the executive — the super-tier President and Vice-Presidents — so
     * when a parentless Group has super-tier members we surface those instead
     * {@see executiveLeadership}. (The model has a flat super_tier flag, not distinct
     * President/VP titles, so all three read "Executive".) A parentless Group with no
     * executives falls through to the ordinary role-based list.
     *
     * @return list<array{role: string, member_id: int, name: string}>
     */
    private function leadership(Group $group): array
    {
        if ($group->parent_id === null) {
            $executive = $this->executiveLeadership($group);

            if ($executive !== []) {
                return $executive;
            }
        }

        $order = array_flip(array_column(Role::cases(), 'value'));

        return $group->memberships
            ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased])
            ->flatMap(fn (GroupMember $membership) => $membership->roles
                ->map(fn (GroupMemberRole $role) => [
                    'role' => $role->role->value,
                    'member_id' => $membership->member->id,
                    'name' => $membership->member->first_name.' '.$membership->member->last_name,
                ]))
            ->sortBy(fn (array $entry) => $order[$entry['role']] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * The root DMV Group's leadership: its super-tier executives (President / VPs),
     * surfaced by org authority since they carry no per-Group role row. Departed
     * members are excluded and entries are ordered by surname for a stable list.
     * The synthetic `executive` role resolves to a label via `group.role.executive`.
     *
     * @return list<array{role: string, member_id: int, name: string}>
     */
    private function executiveLeadership(Group $group): array
    {
        return $group->memberships
            ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased])
            ->filter(fn (GroupMember $membership) => $membership->member->super_tier)
            ->sortBy(fn (GroupMember $membership) => $membership->member->last_name)
            ->map(fn (GroupMember $membership) => [
                'role' => 'executive',
                'member_id' => $membership->member->id,
                'name' => $membership->member->first_name.' '.$membership->member->last_name,
            ])
            ->values()
            ->all();
    }

    /**
     * The Group's roster — the Group-scoped Directory surface (#189). Each living
     * member, A–Z by surname, carrying their within-Group role(s) and standing on
     * top of the centralized {@see MemberResource} payload, so contact PII stays
     * gated behind `viewContact` (ADR-0017) per row and is never hand-built here.
     *
     * Default visibility hides the departed (Resigned / Deceased); Inactive still
     * shows. An officer may flip the "show past members" toggle (`?past=1`) to reveal
     * Resigned so a resigned member can be found and reinstated; Deceased never
     * appears for anyone (#192). The toggle is ignored for a non-officer.
     *
     * Each row carries its membership id and leave window so the officer CRUD can
     * target it, plus a `can_hard_remove` hint — true only when the viewer manages
     * the roster and the membership has no dependent records (the added-in-error
     * case). These extra keys are inert for an ordinary member.
     *
     * @return list<array<string, mixed>>
     */
    private function roster(Request $request, Group $group): array
    {
        // Eager-load what `viewContact` traverses — each member's memberships (with
        // their Group and roles) so the gate resolves in memory — plus the viewer's
        // roles once, mirroring MemberController::show.
        $group->loadMissing([
            'memberships.member.memberships.group',
            'memberships.member.memberships.roles',
        ]);
        $request->user()?->loadMissing('memberships.roles');

        $canManage = $request->user()->can('create', [GroupMember::class, $group]);

        // Deceased is always hidden; Resigned only when an officer asks to see past
        // members. A non-officer can never reveal Resigned, whatever the query says.
        $hidden = $canManage && $request->boolean('past')
            ? [MembershipStatus::Deceased]
            : [MembershipStatus::Resigned, MembershipStatus::Deceased];

        return $group->memberships
            ->whereNotIn('status', $hidden)
            // A–Z by surname, breaking ties on given name (a space sorts ahead of
            // any letter, so "Smith" precedes "Smithson").
            ->sortBy(fn (GroupMember $membership) => mb_strtolower($membership->member->last_name.' '.$membership->member->first_name))
            ->map(fn (GroupMember $membership) => [
                ...(new MemberResource($membership->member))->resolve($request),
                // The member's role badge(s) and standing within *this* Group — the
                // two Group-specific additions over the shared Directory row.
                'group_roles' => $membership->roles
                    ->map(fn (GroupMemberRole $role) => $role->role->value)
                    ->values()
                    ->all(),
                'group_standing' => $membership->status->value,
                // Officer CRUD targeting + the leave window for the change-standing
                // editor. The hard-remove affordance is offered only for a membership
                // with no dependent records — anything else must be resigned.
                'membership_id' => $membership->id,
                'loa_start' => $membership->loa_start?->toDateString(),
                'loa_end' => $membership->loa_end?->toDateString(),
                'can_hard_remove' => $canManage && $membership->roles->isEmpty(),
            ])
            ->values()
            ->all();
    }

    /**
     * The Roster tab's officer-CRUD scaffolding (#192) — withheld from a non-officer.
     * `candidates` is every Member not already in the Group (the add-member search
     * source, id + name only, no contact PII); `assignableRoles` is the Group's
     * capability-valid role set (a role whose backing capability is off is never
     * offered); `showingPast` echoes whether Resigned members are currently revealed.
     *
     * @return array{candidates: list<array{id: int, first_name: string, last_name: string}>, assignableRoles: list<string>, showingPast: bool}
     */
    private function rosterMeta(Request $request, Group $group): array
    {
        if (! $request->user()->can('create', [GroupMember::class, $group])) {
            return ['candidates' => [], 'assignableRoles' => [], 'showingPast' => false];
        }

        $existing = $group->memberships->pluck('member_id')->all();

        $candidates = Member::query()
            ->whereNotIn('id', $existing)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
            ])
            ->all();

        $assignableRoles = collect(Role::cases())
            ->filter(fn (Role $role) => $role->requiredCapability() === null || $group->{$role->requiredCapability()})
            ->map(fn (Role $role) => $role->value)
            ->values()
            ->all();

        return [
            'candidates' => $candidates,
            'assignableRoles' => $assignableRoles,
            'showingPast' => $request->boolean('past'),
        ];
    }

    /**
     * The Group's meetings (#190, #193) — each carrying its location, optional
     * video link, the agenda / minutes / report links, its published/hidden state,
     * and per-meeting `can` management hints.
     *
     * Ordered the way the page is read, not the way the rows were written:
     * **upcoming first, soonest at the top** — the "when do we next meet" answer a
     * member opens the tab for — then the **past, most recent first**, which is
     * how an archive is browsed. `is_upcoming` marks the split so the client can
     * head the two blocks; the boundary is the request's own clock.
     *
     * The published/hidden flag is respected: an ordinary member sees published
     * meetings only, while an officer (Secretary / Chair / super-tier) sees drafts
     * too, so they can finish and publish them. Management authority is Group-scoped
     * — identical for every meeting in the Group — so it is resolved once.
     *
     * @return list<array<string, mixed>>
     */
    private function meetings(Request $request, Group $group): array
    {
        $canManage = $request->user()->can('create', [Meeting::class, $group]);
        $now = CarbonImmutable::now();

        $meetings = $group->meetings()
            ->unless(
                $canManage,
                fn (Builder $query) => $query->published(),
            )
            ->with('links')
            ->orderBy('held_at')
            ->get();

        // Chronological out of the database, then split: the upcoming block keeps
        // that ascending order (next meeting first), the past block is reversed
        // (most recent first).
        [$upcoming, $past] = $meetings->partition(
            fn (Meeting $meeting) => $meeting->held_at->greaterThanOrEqualTo($now),
        );

        // `values()` before mapping: partition and concat both carry the original
        // keys through, and a non-zero-indexed array would serialize as a JSON
        // object instead of the list the client iterates.
        return $upcoming->concat($past->reverse())
            ->values()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'held_at' => $meeting->held_at->toIso8601String(),
                // Which block this meeting belongs to. Resolved server-side against
                // one clock, so the two blocks can never overlap or leave a gap.
                'is_upcoming' => $meeting->held_at->greaterThanOrEqualTo($now),
                'location' => $meeting->location,
                'video_url' => $meeting->video_url,
                'is_published' => $meeting->is_published,
                'links' => $meeting->links
                    ->map(fn (MeetingLink $link) => [
                        'kind' => $link->kind->value,
                        'url' => $link->url,
                    ])
                    ->all(),
                // Group-scoped, so equal for every meeting — the `$canManage`
                // resolved once above drives both edit and delete affordances.
                'can' => [
                    'update' => $canManage,
                    'delete' => $canManage,
                ],
            ])
            ->all();
    }

    /**
     * The Group's Scheduling section (#353, PRD #352, ADR-0021 §1) — the read surface.
     * Returns the viewer's visible Schedules, plus the one opened by permalink if any.
     *
     * Each Schedule is filtered through the SchedulePolicy's per-Schedule `view`, so a
     * draft surfaces only to the Group's schedule admins while a published one follows
     * the Group's listing visibility. Past Schedules stay in the list — nothing is
     * hidden by date.
     *
     * Navigation has two outcomes and no data-dependent branch. A permalink
     * (`$schedule` bound) opens that Schedule after the same `view` check; the bare
     * section URL always shows the list, current and upcoming first. The section
     * behaves like every other section tab — an index, from which a reader picks.
     *
     * @return array{schedules: list<array<string, mixed>>, open: array<string, mixed>|null, roster: list<array<string, mixed>>, shift_kinds: list<array<string, mixed>>}
     */
    private function scheduling(Request $request, Group $group, ?Schedule $schedule): array
    {
        $user = $request->user();
        $today = CarbonImmutable::now()->startOfDay();

        // A permalink opens the addressed Schedule — but only if it belongs to this
        // Group and the viewer may read it. 404 (not 403) so an unreadable draft or a
        // cross-Group id never confirms the Schedule exists. The owning Group is set on
        // the relation so the policy's read check never lazy-loads under strict mode.
        if ($schedule !== null) {
            abort_unless($schedule->group_id === $group->id, 404);
            $schedule->setRelation('group', $group);
            abort_unless($user->can('view', $schedule), 404);

            return [
                'schedules' => [],
                'open' => $this->scheduleDetail($request, $schedule),
                'roster' => $this->assignmentRoster($request, $group),
                'shift_kinds' => $this->shiftKinds($request, $group, $schedule),
            ];
        }

        // The viewer's visible Schedules — a draft only for a schedule admin, a
        // published one within the Group's listing audience. `group` is eager-set so
        // the per-Schedule policy check never lazy-loads under strict mode.
        $visible = $group->schedules()
            ->orderBy('starts_on')
            ->get()
            ->each(fn (Schedule $candidate) => $candidate->setRelation('group', $group))
            ->filter(fn (Schedule $candidate) => $user->can('view', $candidate));

        // The list: current and upcoming first (soonest range first), then past
        // (most recently ended first) — an archive is browsed newest-first.
        [$current, $past] = $visible->partition(fn (Schedule $candidate) => $candidate->isCurrent($today));

        return [
            'schedules' => $current
                ->concat($past->sortByDesc('ends_on'))
                ->values()
                ->map(fn (Schedule $candidate) => [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'starts_on' => $candidate->starts_on->toDateString(),
                    'ends_on' => $candidate->ends_on->toDateString(),
                    'state' => $candidate->state->value,
                    // Which block this Schedule heads under, resolved server-side
                    // against one clock so the two blocks never overlap or gap.
                    'is_past' => ! $candidate->isCurrent($today),
                    'url' => route('groups.scheduling.show', ['group' => $group->slug, 'schedule' => $candidate->id]),
                    'can' => $this->scheduleAuthoring($request, $candidate),
                ])
                ->all(),
            'open' => null,
            // The picker's roster and the kind vocabulary are concerns of an opened
            // Schedule's authoring only; the list view shows no Shift form and so needs
            // neither.
            'roster' => [],
            'shift_kinds' => [],
        ];
    }

    /**
     * One Schedule's detail payload — the read view. Its name, range, state, the
     * as-authored description, the viewer's per-Schedule authoring hints, and the
     * Agenda's Shifts (#355). The Agenda's day-grouping is done client-side on the org
     * wall clock (a pure module the Calendar will share), so the payload ships a flat,
     * start-ordered Shift list and lets the reader's view decide the shape.
     *
     * @return array<string, mixed>
     */
    private function scheduleDetail(Request $request, Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'starts_on' => $schedule->starts_on->toDateString(),
            'ends_on' => $schedule->ends_on->toDateString(),
            'state' => $schedule->state->value,
            'description' => $schedule->description,
            'can' => $this->scheduleAuthoring($request, $schedule),
            'shifts' => $this->shifts($request, $schedule),
            // Other Groups' `open` Shifts the viewer can take, in this Schedule's day range —
            // advertised, attributed, and never mixed into the own list above (#361).
            'foreign' => $this->foreignShifts($request, $schedule),
        ];
    }

    /**
     * A Schedule's Shifts for the Agenda read surface (#355, #357, ADR-0021 §2). Each Shift
     * reads as its instants (UTC on the wire, formatted on the org wall clock client-side),
     * its integer capacity, how many seats are taken, its kind name where the Group uses
     * kinds (null for Reception's shape), and the Members holding its seats.
     *
     * **Sign-up names are visible to every viewer who can read the Schedule, non-members
     * included** (ADR-0017 §6): the section is org-open, a Schedule is a roster of who is on
     * the floor, no more exposing than the Directory. Each seat is routed through the
     * centralized {@see MemberResource} so contact PII stays gated behind `viewContact` and
     * only the name tier surfaces; nothing else about a Sign-up is exposed.
     *
     * The viewer's own participation drives the take/drop affordance: `can.signUp` is the
     * SignUpPolicy's per-Shift verdict (false when the viewer is ineligible or the Shift is
     * full), and `signup_id` is the viewer's own seat on this Shift (null when they hold
     * none) so a drop is one click from where they signed up. Both are UI hints — the Form
     * Requests enforce every write regardless.
     *
     * Eager-loads the Shifts' Sign-ups, their Members and each Member's memberships (with
     * Group and roles) so neither the MemberResource contact gate nor the capacity/seat
     * counts lazy-load under strict mode.
     *
     * @return list<array<string, mixed>>
     */
    private function shifts(Request $request, Schedule $schedule): array
    {
        $viewer = $request->user();

        $shifts = $schedule->shifts()
            ->with(['kind', 'signUps.member.memberships.group', 'signUps.member.memberships.roles'])
            ->orderBy('starts_at')
            ->get()
            // The per-Shift SignUpPolicy check reads `$shift->schedule` (and its Group); set
            // it from the Schedule already in hand so it never lazy-loads under strict mode.
            ->each(fn (Shift $shift) => $shift->setRelation('schedule', $schedule));

        return $shifts
            // A schedule admin (the ShiftPolicy's edit gate) gets the officer affordances —
            // the assign button and each seat's remove target; a plain reader gets neither.
            ->map(fn (Shift $shift) => $this->shiftPayload($request, $shift, $viewer->can('update', $shift)))
            ->all();
    }

    /**
     * The other Groups' `open` Shifts a reader discovers on this Schedule (#361, ADR-0021
     * §Sign-up) — the foreign set. Cross-Group participation is a **read concern: a query,
     * never a relationship** (no join table, no linked-Shift row, no synchronisation). The
     * query gathers `open` Shifts on *other* Groups' published Schedules that fall in this
     * Schedule's day range (so a reader sees them beside the days they are already reading);
     * the per-viewer eligibility — can-read-that-Schedule and both sign-up floors — is the
     * SignUpPolicy's own `create` verdict, so the set is exactly "open *to you*". A Private
     * Group's Shift stays as invisible as the Group itself, and a viewer the floors bar sees
     * none.
     *
     * Foreign Shifts carry **no authoring affordances** for anyone, Scheduler included: they
     * are mapped with `canManage: false`, so no assign button and no seat-removal target
     * appears — a Scheduler cannot edit another Group's data from her own Group's page. Each
     * is attributed to its owning Group by name.
     *
     * @return list<array<string, mixed>>
     */
    private function foreignShifts(Request $request, Schedule $schedule): array
    {
        $viewer = $request->user();
        $rangeStart = CarbonImmutable::instance($schedule->starts_on)->startOfDay();
        $rangeEnd = CarbonImmutable::instance($schedule->ends_on)->endOfDay();

        $candidates = Shift::query()
            ->where('audience', ShiftAudience::Open)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->whereHas('schedule', fn (Builder $query) => $query
                ->where('group_id', '!=', $schedule->group_id)
                ->where('state', ScheduleState::Published))
            ->with(['schedule.group', 'kind', 'signUps.member.memberships.group', 'signUps.member.memberships.roles'])
            ->orderBy('starts_at')
            ->get();

        return $candidates
            // "Open to you" is the SignUpPolicy's own eligibility: the viewer can read that
            // Group's Schedule (a Private Group discloses nothing to a non-member) and clears
            // both floors. Resolved per row against the eager-loaded Schedule and Group.
            ->filter(fn (Shift $shift) => $viewer->can('create', [SignUp::class, $shift]))
            // Never any authoring affordance on a foreign Shift, and attributed to its owner.
            ->map(fn (Shift $shift) => [
                ...$this->shiftPayload($request, $shift, canManage: false),
                'group_name' => $shift->schedule->group->name,
            ])
            ->values()
            ->all();
    }

    /**
     * One Shift's read payload, shared by the owning-Group Agenda and the foreign set (#357,
     * #359, #361). `$canManage` is the schedule-admin verdict for *this* Shift: it reveals the
     * officer affordances — each seat's Sign-up id (the removal target) and the `assign`
     * button — and is always false for a foreign Shift, which carries no authoring affordances
     * for anyone.
     *
     * Sign-up names are visible to every reader who can read the Schedule (ADR-0017 §6),
     * routed through {@see MemberResource} so contact PII stays gated. `signup_id` is the
     * viewer's own seat for a one-click drop; `can.signUp` is the SignUpPolicy verdict folded
     * with a free seat, so the take button shows only where a Sign-up would land.
     *
     * @return array<string, mixed>
     */
    private function shiftPayload(Request $request, Shift $shift, bool $canManage): array
    {
        $viewer = $request->user();
        $taken = $shift->signUps->count();
        $ownSignUp = $shift->signUps->firstWhere('member_id', $viewer->getKey());

        return [
            'id' => $shift->id,
            'starts_at' => $shift->starts_at->toIso8601String(),
            'ends_at' => $shift->ends_at->toIso8601String(),
            'capacity' => $shift->capacity,
            'taken' => $taken,
            'kind' => $shift->kind?->name,
            // The Shift's own authored fields the edit form round-trips: its `audience`
            // (the discovery filter) and the id of its chosen kind (null for Reception's
            // kind-less shape), so the form pre-selects both rather than guessing from the
            // display name. UI hints only — the Form Requests re-validate every write.
            'audience' => $shift->audience->value,
            'shift_kind_id' => $shift->shift_kind_id,
            // The seated Members, names only (contact stays gated per MemberResource). A
            // schedule admin additionally gets each seat's own Sign-up id — the remove target
            // for officer removal (#359), for any seat, not just their own. A plain reader,
            // and every reader of a foreign Shift, never learns another seat's id.
            'signups' => $shift->signUps
                ->map(function (SignUp $signUp) use ($request, $canManage) {
                    $seat = (new MemberResource($signUp->member))->resolve($request);

                    if ($canManage) {
                        $seat['signup_id'] = $signUp->id;
                    }

                    return $seat;
                })
                ->all(),
            // The viewer's own seat on this Shift, for a one-click drop; null if none.
            'signup_id' => $ownSignUp?->id,
            // The affordances this Shift offers the viewer. `signUp` is the self-service
            // verdict — the SignUpPolicy's floors and `audience`, plus a free seat and no seat
            // already held. `assign` is the officer verdict — the schedule-admin gate plus a
            // free seat (capacity binds the Scheduler too, no override). A full Shift shows as
            // full with neither; the write seams re-check each on POST.
            'can' => [
                'signUp' => $ownSignUp === null
                    && $taken < $shift->capacity
                    && $viewer->can('create', [SignUp::class, $shift]),
                'assign' => $canManage && $taken < $shift->capacity,
                // The Shift authoring affordances (#356 front end). `update` is the
                // schedule-admin gate, already resolved as `$canManage`; `delete` folds in
                // the zero-Sign-ups rule (ADR-0021 §2) — a seated Shift must be emptied
                // before it can be cancelled — read from the seats already counted above.
                // Always false for a foreign Shift, which carries no authoring affordances.
                'update' => $canManage,
                'delete' => $canManage && $taken === 0,
            ],
        ];
    }

    /**
     * The placement roster for the officer-assignment picker (#359, ADR-0021 §Sign-up) — the
     * Members a schedule admin may place on this Group's Shifts. Present only for a schedule
     * admin (the same gate that reveals a draft); an ordinary reader gets an empty list and
     * no picker.
     *
     * The list is the Group's own roster narrowed to **placeable** Members — both sign-up
     * floors satisfied ({@see Category::canSignUp()} and {@see MembershipStatus::canSignUp()})
     * — so the picker offers no seat the write seam would reject. It routes through
     * {@see MemberResource::directoryCollection()}, so it carries the name tier only and never
     * asks the contact gate per row, exactly like the Directory.
     *
     * @return list<array<string, mixed>>
     */
    private function assignmentRoster(Request $request, Group $group): array
    {
        if (! $request->user()->can('create', [Schedule::class, $group])) {
            return [];
        }

        $placeable = $group->memberships()
            ->with('member')
            ->get()
            ->filter(fn (GroupMember $membership) => $membership->status->canSignUp()
                && $membership->member->category->canSignUp())
            ->map(fn (GroupMember $membership) => $membership->member)
            ->values();

        return MemberResource::directoryCollection($placeable)->resolve();
    }

    /**
     * The kind vocabulary for the Shift authoring form (#356 front end, ADR-0021 §3) — the
     * Group's active {@see ShiftKind} rows, id and name, the options the kind picker offers a
     * new or edited Shift. Present only for someone who may add a Shift to the opened Schedule
     * (the same schedule-admin gate that reveals the form); a plain reader gets an empty list
     * and no picker. Only *active* kinds are offered — an inactive kind still labels the Shifts
     * already carrying it, but is no longer put on new ones ({@see ShiftKind::scopeActive}).
     *
     * @return list<array<string, mixed>>
     */
    private function shiftKinds(Request $request, Group $group, Schedule $schedule): array
    {
        if (! $request->user()->can('create', [Shift::class, $schedule])) {
            return [];
        }

        return $group->shiftKinds()
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ShiftKind $kind) => ['id' => $kind->id, 'name' => $kind->name])
            ->all();
    }

    /**
     * The viewer's per-Schedule authoring hints (#354) — UI cues only, the server
     * enforces every mutation in the Form Requests regardless. `update` gates the
     * inline edit; `publish` / `unpublish` gate the two `state` transitions (only one
     * applies at a time, by current state); `delete` gates removal. A non-admin gets
     * all four false.
     *
     * @return array<string, bool>
     */
    private function scheduleAuthoring(Request $request, Schedule $schedule): array
    {
        $user = $request->user();

        return [
            'update' => $user->can('update', $schedule),
            'publish' => $user->can('publish', $schedule),
            'unpublish' => $user->can('unpublish', $schedule),
            'delete' => $user->can('delete', $schedule),
        ];
    }
}
