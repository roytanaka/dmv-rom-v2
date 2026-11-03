<?php

namespace App\Actions;

use App\Enums\Category;
use App\Enums\DepartureReason;
use App\Enums\MembershipStatus;
use App\Models\CategoryChange;
use App\Models\Member;
use App\Support\Notices\MenteeActivatedNoticeWriter;
use App\Support\Notices\StandingChangeNoticeWriter;
use App\Support\OrgTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one path that moves a Member's Category (#920, ADR-0038 §4). Each change is a dated
 * Category history entry; the newest entry sets `category`, `status_date` and the departure
 * reason, as the Legacy app recomputed them. Nothing else assigns the Category outside the seeders
 * and the import.
 *
 * Side effects run when an added entry actually changes the Category: Resigned or Deceased tells
 * the Chairs and departs the Memberships; Provisional or Pre-active to Active plans the next
 * Service award and tells the mentor. Removing an entry recomputes but runs none of them.
 */
class ChangeCategory
{
    public function __construct(
        private StandingChangeNoticeWriter $standingNotice,
        private MenteeActivatedNoticeWriter $mentorNotice,
    ) {}

    /**
     * Add an entry and recompute. The reason is kept only for Resigned and Withdrawn.
     */
    public function change(Member $member, Category $category, CarbonInterface $date, ?DepartureReason $reason = null, ?Member $actor = null): CategoryChange
    {
        return DB::transaction(function () use ($member, $category, $date, $reason, $actor): CategoryChange {
            $previous = $member->category;
            $change = $this->write($member, $category->value, $date, $this->carriesReason($category) ? $reason : null, $actor);
            $this->recompute($member);

            if ($member->category !== $previous) {
                $this->sideEffects($member, $previous, $member->category, $date);
            }

            return $change;
        });
    }

    /**
     * Reinstate (ADR-0038 §4): the Reinstate marker, then Active, both dated today. Sends nothing.
     */
    public function reinstate(Member $member, ?Member $actor = null): void
    {
        DB::transaction(function () use ($member, $actor): void {
            $today = OrgTime::now()->startOfDay();
            $this->write($member, CategoryChange::REINSTATE, $today, null, $actor);
            $this->change($member, Category::Active, $today, null, $actor);
        });
    }

    /** Remove a wrong entry and recompute from what remains. */
    public function remove(CategoryChange $change): void
    {
        DB::transaction(function () use ($change): void {
            $member = $change->member;
            $change->delete();
            $this->recompute($member);
        });
    }

    private function write(Member $member, string $category, CarbonInterface $date, ?DepartureReason $reason, ?Member $actor): CategoryChange
    {
        return $member->categoryChanges()->create([
            'category' => $category,
            'date' => $date->toDateString(),
            'departure_reason' => $reason,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * The newest entry that names a Category becomes the Category, the status date and the
     * reason. With no such entry left the Category stays as it was and the status date clears.
     */
    private function recompute(Member $member): void
    {
        $newest = $member->categoryChanges()
            ->newestFirst()
            ->get()
            ->first(fn (CategoryChange $change) => $change->toCategory() !== null);

        $member->forceFill([
            'category' => $newest?->toCategory() ?? $member->category,
            'status_date' => $newest?->date,
            'departure_reason' => $newest?->departure_reason,
        ])->save();
    }

    private function sideEffects(Member $member, Category $previous, Category $new, CarbonInterface $date): void
    {
        if (in_array($new, [Category::Resigned, Category::Deceased], true)) {
            // Tell first: the writer skips Memberships already departed.
            $this->standingNotice->write($member, $previous, $new, $date);
            $this->departMemberships($member, $new);
        }

        if ($new === Category::Active && in_array($previous, [Category::Provisional, Category::PreActive], true)) {
            if ($member->entry_year !== null) {
                $member->forceFill(['next_award_year' => $member->entry_year + 5])->save();
            }

            // The mentor is known once an Applicant links to this Member (#914); until then none.
            $this->mentorNotice->write($member, null);
        }
    }

    /**
     * Resigned departs every Membership not already resigned or deceased; Deceased every one not
     * already deceased.
     */
    private function departMemberships(Member $member, Category $new): void
    {
        $status = $new === Category::Deceased ? MembershipStatus::Deceased : MembershipStatus::Resigned;
        $departed = $new === Category::Deceased
            ? [MembershipStatus::Deceased]
            : [MembershipStatus::Resigned, MembershipStatus::Deceased];

        $member->memberships()
            ->whereNotIn('status', array_map(fn (MembershipStatus $s) => $s->value, $departed))
            ->get()
            ->each(fn ($membership) => $membership->update(['status' => $status]));
    }

    private function carriesReason(Category $category): bool
    {
        return in_array($category, [Category::Resigned, Category::Withdrawn], true);
    }
}
