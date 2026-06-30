<?php

namespace App\Http\Controllers;

use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
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
    public function show(Group $group, ?string $section = null): Response
    {
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
            'section' => $section ?? 'overview',
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
}
