<?php

namespace App\Http\Controllers;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\MemberResource;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\MeetingLink;
use App\Models\Member;
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
        $section ??= 'overview';

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
                // plus the muted "soon" stubs for a capability the Group runs).
                'capabilities' => [
                    'meetings' => $group->has_meetings,
                    'documents' => $group->has_documents,
                    'scheduling' => $group->has_scheduling,
                    'content' => $group->has_content_catalog,
                    'hours' => $group->has_hours_stats,
                ],
            ],
            'section' => $section,
            // UI hints only — the server enforces in the Form Requests. `update`
            // drives the Overview's inline About Us edit and banner picker;
            // `createMeeting` drives the Meetings tab's "New meeting" affordance;
            // `manageRoster` drives the Roster tab's officer CRUD (#192).
            'can' => [
                'update' => $request->user()->can('update', $group),
                'createMeeting' => $request->user()->can('create', [Meeting::class, $group]),
                'manageRoster' => $request->user()->can('create', [GroupMember::class, $group]),
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
     * The Group's meetings (#190, #193) — upcoming and past, newest first, each
     * carrying its location, optional video link, the agenda / minutes / report
     * links, its published/hidden state, and per-meeting `can` management hints.
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

        return $group->meetings()
            ->unless(
                $canManage,
                fn (Builder $query) => $query->published(),
            )
            ->with('links')
            ->orderByDesc('held_at')
            ->get()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'held_at' => $meeting->held_at->toIso8601String(),
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
}
