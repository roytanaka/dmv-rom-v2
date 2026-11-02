<?php

namespace App\Http\Controllers;

use App\Enums\Reply;
use App\Http\Requests\SetGuestReplyRequest;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Host's Replies page (ADR-0034 §10, §11): who said what, with totals, and a Reply set
 * for a Guest. Only the Host and the super-tier reach it. No row on the page carries an
 * email address.
 */
class GuestReplyController extends Controller
{
    /**
     * Every Guest with their Reply and Options, Accepted first as in the old app, then by
     * surname. The Print button hands this same page to the browser.
     */
    public function index(Request $request, Invitation $invitation): Response
    {
        Gate::authorize('manageGuests', $invitation);

        $guests = $invitation->guests()->with(['member', 'options'])->get();
        $options = $invitation->options()->orderBy('id')->get();

        return Inertia::render('invitations/Replies', [
            'invitation' => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'date' => $invitation->date->toDateString(),
                'start_time' => $invitation->start_time,
                'end_time' => $invitation->end_time,
                'options' => $options->map(fn (Option $option): array => ['id' => $option->id, 'name' => $option->name])->all(),
            ],
            'totals' => [
                'accepted' => $guests->where('reply', Reply::Accepted)->count(),
                'declined' => $guests->where('reply', Reply::Declined)->count(),
                'no_reply' => $guests->where('reply', Reply::NoReply)->count(),
                'options' => $options->map(fn (Option $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'count' => $guests->filter(fn (Guest $guest): bool => $guest->options->contains($option))->count(),
                ])->all(),
            ],
            'guests' => $guests
                ->sortBy(fn (Guest $guest): array => [
                    array_search($guest->reply, [Reply::Accepted, Reply::Declined, Reply::NoReply]),
                    $guest->member->surnameKey(),
                ])
                ->map(fn (Guest $guest): array => [
                    'id' => $guest->id,
                    'member' => $guest->member_id,
                    'name' => $guest->member->fullName(),
                    'reply' => $guest->reply->value,
                    'options' => $guest->options->pluck('id')->sort()->values()->all(),
                ])
                ->values()->all(),
            'candidates' => Member::query()->inDirectory()
                ->whereNotIn('id', $guests->pluck('member_id'))
                ->get()
                ->sortBy(fn (Member $member): string => $member->surnameKey())
                ->map(fn (Member $member): array => ['id' => $member->id, 'name' => $member->fullName()])
                ->values()->all(),
        ]);
    }

    /**
     * Record an answer given to the Host, even after Closed. A Member not on the list
     * becomes a Guest. A Host-set Reply goes straight to `Guest::setReply`, so it skips the
     * Guest's own checks.
     */
    public function update(SetGuestReplyRequest $request, Invitation $invitation): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->integer('member'));

        $invitation->addGuests(collect([$member]));

        $invitation->guestFor($member)->setReply(
            $request->enum('reply', Reply::class),
            $request->validated('options', []),
        );

        return to_route('invitations.replies', ['invitation' => $invitation->id]);
    }
}
