<?php

namespace App\Http\Controllers;

use App\Actions\RemovePersonalData;
use App\Enums\MemberList;
use App\Http\Requests\RemovePersonalDataRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Remove personal data (#921, ADR-0038 §6): the page previews the counts for a year (a query
 * parameter, default seven years ago); the run blanks the qualifying Members and opens the Past
 * list, where they now stand.
 */
class RemovePersonalDataController extends Controller
{
    public function show(Request $request, RemovePersonalData $removal): Response
    {
        Gate::authorize('removePersonalData', Member::class);

        $requested = filter_var($request->query('year'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => 2100]]);
        $year = $requested === false ? RemovePersonalData::defaultYear() : $requested;

        return Inertia::render('officer/members/RemovePersonalData', [
            'year' => $year,
            'memberCount' => $removal->members($year)->count(),
            'applicantCount' => $removal->applicantCount($year),
        ]);
    }

    public function store(RemovePersonalDataRequest $request, RemovePersonalData $removal): RedirectResponse
    {
        $removal->run((int) $request->validated('year'), $request->user());

        return redirect(route('officer.members', ['list' => MemberList::Past->urlWord()], false));
    }
}
