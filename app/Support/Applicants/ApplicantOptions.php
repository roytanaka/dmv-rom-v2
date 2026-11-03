<?php

namespace App\Support\Applicants;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Collection;

/**
 * The choices the Applicant forms offer (#928, ADR-0038 §8), read by the pages and checked by the
 * Form Requests from one source. Mentors are the holder's active roster, its Chair first, as the
 * Legacy app's select; placements are the active Groups below the org root.
 */
final class ApplicantOptions
{
    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function mentors(): Collection
    {
        $holder = Group::stewardOf(StewardshipFunction::Applicants);

        if ($holder === null) {
            return collect();
        }

        return GroupMember::query()
            ->where('group_id', $holder->id)
            ->whereIn('status', MembershipStatus::belongingValues())
            ->with(['member', 'roles'])
            ->get()
            ->sortBy(fn (GroupMember $membership) => [
                $membership->roles->contains('role', Role::Chair) ? 0 : 1,
                $membership->member->last_name,
                $membership->member->first_name,
            ])
            ->map(fn (GroupMember $membership) => ['id' => $membership->member->id, 'name' => $membership->member->fullName()])
            ->values();
    }

    /** @return list<int> */
    public static function mentorIds(): array
    {
        return self::mentors()->pluck('id')->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function placements(): Collection
    {
        return Group::query()
            ->active()
            ->where('slug', '!=', Group::ROOT_SLUG)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Group $group) => ['id' => $group->id, 'name' => $group->name])
            ->values();
    }

    /** @return list<int> */
    public static function placementIds(): array
    {
        return self::placements()->pluck('id')->all();
    }
}
