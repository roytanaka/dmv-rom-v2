<?php

namespace App\Support\Notices;

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\StandingChanged;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The standing-change Notice writer (#485, spec #479, ADR-0024 §8). When a Member's DMV-wide
 * {@see Category} moves into a departure — Resigned or Deceased — every Chair of every Group
 * where that Member's Membership is not already departed is told, so the Chair can update their
 * own roster. It writes one Notice {@see Delivery} per Chair and nothing else; the
 * Drain sends them, rendering each in the Chair's saved locale from the row's payload snapshot.
 *
 * The trigger is the member-administration standing-change action (its own spec), which calls
 * {@see write} guarded by nothing more than "the Category changed and the new value is a
 * departure" — there is no observer, no state machine, and no sent record for the guard, so a
 * repeated identical save writes nothing while Resigned-then-Deceased writes twice.
 */
class StandingChangeNoticeWriter
{
    /**
     * Write the Notice Deliveries for one standing change, or nothing when the change is not a
     * departure worth announcing.
     *
     * @param  Member  $member  the Member whose standing changed
     * @param  Category  $previous  their Category before the change
     * @param  Category  $new  their Category after it
     * @param  CarbonInterface  $effectiveDate  the date the change takes effect, named in the mail
     */
    public function write(Member $member, Category $previous, Category $new, CarbonInterface $effectiveDate): void
    {
        if ($previous === $new || ! $this->isDeparture($new)) {
            return;
        }

        foreach ($this->groupsToTell($member, $new) as $group) {
            $snapshot = $this->snapshot($member, $group, $new, $effectiveDate);

            foreach ($this->chairsOf($group) as $chair) {
                // The no-email flag silences every mail, checked once here — the sole point a
                // Notice Delivery is written (ADR-0024 §9). A flagged Chair gets no row.
                if ($chair->no_email) {
                    continue;
                }

                Delivery::create([
                    'kind' => DeliveryKind::Notice,
                    'member_id' => $chair->getKey(),
                    'email' => $chair->email,
                    'payload' => $snapshot,
                    'state' => DeliveryState::Pending,
                    'next_attempt_at' => now(),
                ]);
            }
        }
    }

    /**
     * The Groups whose Chairs hear about this change: every Group where the Member's Membership
     * is not itself already departed (ADR-0024 §8). Resigned skips a Membership already resigned
     * or deceased; Deceased skips only one already deceased — so a Chair whose roster shows the
     * Member resigned is still told of a death.
     *
     * @return Collection<int, Group>
     */
    private function groupsToTell(Member $member, Category $new): Collection
    {
        $departed = $new === Category::Deceased
            ? [MembershipStatus::Deceased]
            : [MembershipStatus::Resigned, MembershipStatus::Deceased];

        return $member->memberships()
            ->with('group')
            ->get()
            ->reject(fn (GroupMember $membership): bool => in_array($membership->status, $departed, true))
            ->map(fn (GroupMember $membership): Group => $membership->group);
    }

    /**
     * The Chair-role holders of a Group — the recipients. Co-chairs each hold their own
     * {@see Role::Chair} row, so each is returned once and each gets a Delivery.
     *
     * @return Collection<int, Member>
     */
    private function chairsOf(Group $group): Collection
    {
        return $group->memberships()
            ->with(['member', 'roles'])
            ->get()
            ->filter(fn (GroupMember $membership): bool => $membership->roles->contains('role', Role::Chair))
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->values();
    }

    /**
     * Freeze what the Notice names into a payload the Drain can render from — the Member may be
     * gone or further changed by then. Shape mirrors {@see StandingChanged::fromSnapshot}; the
     * `notice` discriminator tells the Drain this Notice is a standing change, not a cancellation.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Member $member, Group $group, Category $new, CarbonInterface $effectiveDate): array
    {
        return [
            'notice' => StandingChanged::NOTICE_TYPE,
            'member' => [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
            ],
            'group' => [
                'name' => $group->name,
                'slug' => $group->slug,
            ],
            'standing' => $new->value,
            'effective_date' => $effectiveDate->toIso8601String(),
        ];
    }

    /**
     * The two Categories this Notice fires for. Withdrawn is a departure the org records but does
     * not announce (ADR-0024 §8: "Leave of absence, reinstatement, and Withdrawn send nothing").
     */
    private function isDeparture(Category $category): bool
    {
        return in_array($category, [Category::Resigned, Category::Deceased], true);
    }
}
