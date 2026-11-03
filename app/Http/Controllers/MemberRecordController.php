<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberRecordRequest;
use App\Http\Resources\MemberResource;
use App\Models\BoardPosition;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Models\ServiceAward;
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
            'lastUpdated' => $member->updatedBy === null ? null : [
                'name' => $member->updatedBy->fullName(),
                'date' => $member->updated_at->timezone(config('app.org_timezone'))->toDateString(),
            ],
        ]);
    }

    public function update(UpdateMemberRecordRequest $request, Member $member): RedirectResponse
    {
        $member->fill($request->safe()->except('new_note'));

        // A new last award year plans the next one (ADR-0038 §10). A year sent alongside it is
        // the admin's override, even when it is the year already stored.
        if ($member->isDirty('last_award_year') && $member->last_award_year !== null && ! $request->has('next_award_year')) {
            $member->next_award_year = $member->last_award_year + 5;
        }

        if ($member->isDirty('email')) {
            $member->email_verified_at = null;
        }

        if (filled($request->validated('new_note'))) {
            $member->prependNote($request->validated('new_note'), $request->user());
        }

        $member->updated_by = $request->user()->id;
        $member->save();

        return back();
    }
}
