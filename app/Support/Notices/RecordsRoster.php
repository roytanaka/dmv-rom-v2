<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * The Records Audience (ADR-0038 §8, §9): the belonging roster of the Group that holds member
 * administration. Empty when no Group holds it. {@see self::writeNotice()} writes a Notice to it; a caller
 * that writes its own Deliveries checks the no-email flag itself.
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

    /**
     * Write one Notice {@see Delivery} of the payload to each member of the Records Audience,
     * skipping the no-email flag.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function writeNotice(array $payload): void
    {
        self::members()
            ->reject(fn (Member $member): bool => $member->no_email)
            ->each(fn (Member $member) => Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $member->getKey(),
                'email' => $member->email,
                'payload' => $payload,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]));
    }
}
