<?php

namespace App\Support\Tours;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Qualification;
use App\Models\Tour;
use App\Support\OrgTime;

/**
 * The Tours section's payloads (#789, #792, ADR-0033 §4, §5), built by the Group page once its
 * gates have passed: the Tours read page ({@see self::toursPage()}) and the two qualification
 * screens, by Tour ({@see self::byTour()}) and by Member ({@see self::byMember()}). Tours come
 * in the Group's order ({@see Tour::scopeOrdered()}), Members by surname.
 */
class TourPayloads
{
    /**
     * The Tours page: each active Tour, open to all marked, with the Members holding an active
     * qualification for it, by surname, and their Last vet dates. Inactive qualifications and
     * retired Tours are left out.
     *
     * @return list<array{id: int, name: string, openToAll: bool, members: list<array{memberId: int, name: string, lastVetDate: string|null}>}>
     */
    public static function toursPage(Group $group): array
    {
        return $group->tours()->active()->ordered()
            ->with(['qualifications' => fn ($query) => $query->where('active', true)->with('membership.member')])
            ->get()
            ->map(fn (Tour $tour): array => [
                'id' => $tour->id,
                'name' => $tour->name,
                'openToAll' => $tour->open_to_all,
                'members' => $tour->qualifications
                    ->sortBy(fn (Qualification $qualification) => $qualification->membership->member->surnameKey())
                    ->map(fn (Qualification $qualification): array => [
                        'memberId' => $qualification->membership->member_id,
                        'name' => $qualification->membership->member->fullName(),
                        'lastVetDate' => $qualification->last_vet_date?->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * The by-Tour screen: the Members holding the Tour, active and inactive apart, each sorted by
     * surname, and the picker's candidates — current Members of the Group (a standing that counts
     * as belonging) not already holding the Tour actively. An inactive holder stays a candidate:
     * adding them reactivates the row.
     *
     * @return array<string, mixed>
     */
    public static function byTour(Group $group, Tour $tour): array
    {
        $qualifications = $tour->qualifications()->with('membership.member')->get()
            ->sortBy(fn (Qualification $qualification) => $qualification->membership->member->surnameKey());

        $row = fn (Qualification $qualification): array => [
            'id' => $qualification->id,
            'membershipId' => $qualification->group_member_id,
            'memberId' => $qualification->membership->member_id,
            'name' => $qualification->membership->member->fullName(),
            'standing' => $qualification->membership->status->value,
            'lastVetDate' => $qualification->last_vet_date?->toDateString(),
        ];

        $heldActively = $qualifications->where('active', true)->pluck('group_member_id')->all();

        return [
            'view' => 'tour',
            'today' => OrgTime::today()->toDateString(),
            'tour' => ['id' => $tour->id, 'name' => $tour->name, 'active' => $tour->active],
            'active' => $qualifications->where('active', true)->map($row)->values()->all(),
            'inactive' => $qualifications->where('active', false)->map($row)->values()->all(),
            'candidates' => $group->memberships()->with('member')->get()
                ->filter(fn (GroupMember $membership): bool => $membership->status->countsAsBelonging()
                    && ! in_array($membership->id, $heldActively, true))
                ->sortBy(fn (GroupMember $membership) => $membership->member->surnameKey())
                ->map(fn (GroupMember $membership): array => [
                    'membershipId' => $membership->id,
                    'name' => $membership->member->last_name.', '.$membership->member->first_name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The by-Member screen: the Tours the Membership holds, active and inactive apart, in the
     * Group's Tour order, and the picker's candidates — the Group's active Tours not already held
     * actively. A Member who is no longer current gets no candidates.
     *
     * @return array<string, mixed>
     */
    public static function byMember(Group $group, GroupMember $membership): array
    {
        $membership->loadMissing('member');
        $qualifications = $membership->qualifications()->with('tour')->get()
            ->sortBy(fn (Qualification $qualification) => [$qualification->tour->sort_order, $qualification->tour->name]);

        $row = fn (Qualification $qualification): array => [
            'id' => $qualification->id,
            'tourId' => $qualification->tour_id,
            'name' => $qualification->tour->name,
            'tourActive' => $qualification->tour->active,
            'lastVetDate' => $qualification->last_vet_date?->toDateString(),
        ];

        $current = $membership->status->countsAsBelonging();
        $heldActively = $qualifications->where('active', true)->pluck('tour_id')->all();

        return [
            'view' => 'member',
            'today' => OrgTime::today()->toDateString(),
            'member' => [
                'membershipId' => $membership->id,
                'memberId' => $membership->member_id,
                'name' => $membership->member->fullName(),
                'standing' => $membership->status->value,
                'current' => $current,
            ],
            'active' => $qualifications->where('active', true)->map($row)->values()->all(),
            'inactive' => $qualifications->where('active', false)->map($row)->values()->all(),
            'candidates' => $current
                ? $group->tours()->active()->ordered()->whereNotIn('id', $heldActively)->get()
                    ->map(fn (Tour $tour): array => ['tourId' => $tour->id, 'name' => $tour->name])
                    ->all()
                : [],
        ];
    }
}
