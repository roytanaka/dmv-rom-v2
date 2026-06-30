<?php

namespace App\Http\Controllers;

use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Http\Resources\MemberResource;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\MeetingLink;
use Illuminate\Database\Eloquent\Builder;
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

        // The Meetings list is members-only (MeetingPolicy), unlike the org-open
        // Overview and Roster — a non-member visiting the section is forbidden.
        // Load the viewer's memberships first so the gate resolves in memory
        // (strict mode forbids the lazy load), then authorize.
        if ($section === 'meetings') {
            $request->user()->loadMissing('memberships');
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
            // The Roster tab's payload is resolved only when that tab is active —
            // its per-row contact gating eager-loads each member's memberships, work
            // the Overview never needs.
            'roster' => $section === 'roster' ? $this->roster($request, $group) : [],
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
     * The Group's officers, by role — the "leadership at a glance" list. One entry
     * per (role, member), ordered by the spine's role catalogue so Chair leads.
     * Names link through to profiles on the page; departed members are excluded.
     *
     * @return list<array{role: string, member_id: int, name: string}>
     */
    private function leadership(Group $group): array
    {
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
     * The Group's roster — the Group-scoped Directory surface (#189). Each living
     * member, A–Z by surname, carrying their within-Group role(s) and standing on
     * top of the centralized {@see MemberResource} payload, so contact PII stays
     * gated behind `viewContact` (ADR-0017) per row and is never hand-built here.
     *
     * Default visibility hides only the departed (Resigned / Deceased); Inactive
     * still shows. The officer "show past members" toggle that reveals Resigned
     * lands with the roster-CRUD slice (#192) — this is the read-only default view.
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

        return $group->memberships
            ->whereNotIn('status', [MembershipStatus::Resigned, MembershipStatus::Deceased])
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
            ])
            ->values()
            ->all();
    }

    /**
     * The Group's meetings (#190) — upcoming and past, newest first, each carrying
     * its location, optional video link, and the agenda / minutes / report links.
     *
     * The published/hidden flag is respected: an ordinary member sees published
     * meetings only. The super-tier sees drafts too (it sees everything); the
     * officer drafting view lands with the meetings-CRUD slice (#193).
     *
     * @return list<array<string, mixed>>
     */
    private function meetings(Request $request, Group $group): array
    {
        return $group->meetings()
            ->when(
                ! $request->user()->isAllDmv(),
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
                'links' => $meeting->links
                    ->map(fn (MeetingLink $link) => [
                        'kind' => $link->kind->value,
                        'url' => $link->url,
                    ])
                    ->all(),
            ])
            ->all();
    }
}
