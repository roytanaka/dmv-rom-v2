<?php

namespace App\Http\Controllers;

use App\Enums\MemberList;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Support\RouteSegments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Member lists (#919, ADR-0038 §5): Officer Tools → Members. One route with a {list} word,
 * in the page's language; a bare /officer/members opens Current. Sorted by last name, searched by
 * its start, as the Legacy app's "Search for Last Name". Applicants is a placeholder until #914.
 */
class MemberListController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request, ?string $list = null): Response
    {
        Gate::authorize('administer-members');

        $memberList = $list === null
            ? MemberList::Current
            : MemberList::tryFrom(RouteSegments::canonical($list, app()->getLocale()));

        abort_if($memberList === null, 404);

        $members = $memberList->members();

        if ($members === null) {
            return Inertia::render('ComingSoon');
        }

        $query = $request->query('q');
        $search = is_string($query) && trim($query) !== '' ? trim($query) : null;

        return Inertia::render('officer/members/Index', [
            'list' => $memberList->value,
            'tabs' => array_map(fn (MemberList $tab) => [
                'key' => $tab->value,
                'href' => $this->href($tab),
            ], MemberList::cases()),
            'actions' => $memberList->rowActions(),
            'canSendEmail' => $memberList->offersSendEmail(),
            'search' => $search,
            'members' => $members
                ->when($search, fn ($rows, string $term) => $rows->whereLike('last_name', addcslashes($term, '%_\\').'%'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Member $member) => (new MemberResource($member))->recordsListRow()),
        ]);
    }

    private function href(MemberList $list): string
    {
        return route('officer.members', $list === MemberList::Current ? [] : ['list' => $list->urlWord()], false);
    }
}
