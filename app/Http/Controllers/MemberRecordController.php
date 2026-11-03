<?php

namespace App\Http\Controllers;

use App\Actions\UpdateMemberRecord;
use App\Enums\Category;
use App\Enums\DepartureReason;
use App\Http\Requests\UpdateMemberRecordRequest;
use App\Http\Resources\MemberResource;
use App\Models\BoardPosition;
use App\Models\CategoryChange;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Models\ServiceAward;
use App\Support\OrgTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Member record (#917, ADR-0038 §2, §3): the Records office's page for one Member, every
 * field but Category. Open to member administration and the super-tier.
 */
class MemberRecordController extends Controller
{
    public function show(Request $request, Member $member): Response
    {
        Gate::authorize('administer', $member);

        $member->load([
            'memberships.group',
            'memberships.roles',
            'updatedBy',
            'languages' => fn ($query) => $query->orderBy('id'),
            'boardPositions' => fn ($query) => $query->orderByDesc('id'),
        ])->loadCount('signIns');

        return Inertia::render('officer/members/Record', [
            'member' => (new MemberResource($member))->withRecordsFields(),
            // An inactive category stays on offer for the Member who already holds it.
            'feeCategories' => FeeCategory::query()
                ->where(fn ($query) => $query->where('active', true)->orWhere('code', $member->fee_category))
                ->orderBy('id')
                ->get(['code', 'name', 'amount']),
            'serviceAwards' => ServiceAward::query()->where('active', true)->orderBy('years')->get(['code', 'years']),
            'boardPositionSuggestions' => BoardPosition::query()->distinct()->orderBy('position')->pluck('position'),
            'signInsCount' => $member->sign_ins_count,
            // The Category history, newest first (#920, ADR-0038 §4).
            'categoryHistory' => $member->categoryChanges()->newestFirst()->get()
                ->map(fn (CategoryChange $change) => [
                    'id' => $change->id,
                    'category' => $change->category,
                    'date' => $change->date->toDateString(),
                    'departure_reason' => $change->departure_reason?->value,
                ]),
            'categories' => array_map(fn (Category $category) => $category->value, Category::cases()),
            'departureReasons' => array_map(fn (DepartureReason $reason) => $reason->value, DepartureReason::cases()),
            'lastUpdated' => $member->updatedBy === null ? null : [
                'name' => $member->updatedBy->fullName(),
                'date' => OrgTime::toLocal($member->updated_at)->toDateString(),
            ],
        ]);
    }

    public function update(UpdateMemberRecordRequest $request, UpdateMemberRecord $action, Member $member): RedirectResponse
    {
        $action->update(
            $member,
            $request->safe()->except('new_note'),
            $request->has('next_award_year'),
            $request->validated('new_note'),
            $request->user(),
        );

        return back();
    }
}
