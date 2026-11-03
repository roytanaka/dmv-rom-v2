<?php

namespace App\Support\Notices;

use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * The Records Audience (ADR-0038 §8, §9): the belonging roster of the Group that holds member
 * administration. Empty when no Group holds it. The no-email flag is the caller's to check, at the
 * point it writes the Delivery.
 */
class RecordsRoster
{
    /**
     * @return Collection<int, Member>
     */
    public static function members(): Collection
    {
        $records = Group::stewardOf(StewardshipFunction::MemberAdmin);

        if ($records === null) {
            return collect();
        }

        return $records->memberships()
            ->whereIn('status', MembershipStatus::belongingValues())
            ->with('member')
            ->get()
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->unique('id')
            ->values();
    }
}
