<?php

namespace App\Http\Controllers;

use App\Enums\InvitationState;
use App\Http\Requests\DeleteInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Invitations page (ADR-0034 §13): My invitations for every Member, Hosting for
 * Members who may host. The `can` props are UI hints only; the server enforces every
 * action through the InvitationPolicy.
 */
class InvitationController extends Controller
{
    /**
     * My invitations: the Invitations this Member can reply to, soonest first, each with
     * their own Reply. The Guest comes from the signed-in Member.
     */
    public function index(Request $request): Response
    {
        $guests = Guest::query()
            ->where('member_id', $request->user()->id)
            ->whereHas('invitation', fn ($query) => $query->takingReplies())
            ->with('invitation.host')
            ->get()
            ->sortBy(fn (Guest $guest): string => $guest->invitation->date->toDateString());

        return Inertia::render('invitations/Mine', [
            'can' => ['host' => $request->user()->can('create', Invitation::class)],
            'invitations' => $guests->map(fn (Guest $guest): array => [
                'id' => $guest->invitation->id,
                'name' => $guest->invitation->name,
                'date' => $guest->invitation->date->toDateString(),
                'start_time' => $guest->invitation->start_time,
                'end_time' => $guest->invitation->end_time,
                'host' => $guest->invitation->host->fullName(),
                'reply' => $guest->reply->value,
            ])->values()->all(),
        ]);
    }

    /**
     * One Invitation as a Guest reads it. It carries the viewer's own Reply and no one
     * else's (ADR-0034 §11). The Reply comes from the signed-in Member.
     */
    public function show(Request $request, Invitation $invitation): Response
    {
        Gate::authorize('view', $invitation);

        $guest = $invitation->guestFor($request->user());

        return Inertia::render('invitations/Show', [
            'invitation' => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'date' => $invitation->date->toDateString(),
                'start_time' => $invitation->start_time,
                'end_time' => $invitation->end_time,
                'description' => $invitation->description,
                'host' => $invitation->host->fullName(),
                'closed' => $invitation->isClosed(),
                'options' => $invitation->options()->orderBy('id')->get(['id', 'name'])->toArray(),
            ],
            'guest' => $guest === null ? null : [
                'reply' => $guest->reply->value,
                'options' => $guest->options()->pluck('options.id')->all(),
            ],
            'can' => ['reply' => $invitation->takesReplyFrom($request->user())],
        ]);
    }

    /**
     * The Hosting tab: the Member's own Invitations, past ones included. The
     * super-tier sees every Invitation here, which is how they manage them.
     */
    public function hosting(Request $request): Response
    {
        Gate::authorize('create', Invitation::class);

        $viewer = $request->user();

        $invitations = Invitation::with('host')
            ->when(! $viewer->isAllDmv(), fn ($query) => $query->where('host_id', $viewer->id))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('invitations/Hosting', [
            'invitations' => $invitations->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'date' => $invitation->date->toDateString(),
                'start_time' => $invitation->start_time,
                'end_time' => $invitation->end_time,
                'state' => $invitation->state->value,
                'host' => $invitation->host->fullName(),
                'can' => [
                    'update' => $viewer->can('update', $invitation),
                    'delete' => $viewer->can('delete', $invitation),
                    'guests' => $viewer->can('manageGuests', $invitation),
                ],
            ])->all(),
        ]);
    }

    /**
     * The empty form for a new Invitation.
     */
    public function create(): Response
    {
        Gate::authorize('create', Invitation::class);

        return Inertia::render('invitations/Form', ['invitation' => null]);
    }

    /**
     * Create a Draft. The Host is the signed-in Member, never a request field.
     */
    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $invitation = new Invitation;
        $invitation->host()->associate($request->user());
        $this->save($invitation, $request->validated());

        return to_route('invitations.hosting');
    }

    /**
     * The form for an existing Invitation.
     */
    public function edit(Invitation $invitation): Response
    {
        Gate::authorize('update', $invitation);

        return Inertia::render('invitations/Form', [
            'invitation' => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'date' => $invitation->date->toDateString(),
                'start_time' => $invitation->start_time,
                'end_time' => $invitation->end_time,
                'description' => $invitation->description,
                'options' => $invitation->options()->orderBy('id')->get(['id', 'name'])->toArray(),
            ],
        ]);
    }

    /**
     * Save an edit. The Host and the state are fixed here.
     */
    public function update(UpdateInvitationRequest $request, Invitation $invitation): RedirectResponse
    {
        $this->save($invitation, $request->validated());

        return to_route('invitations.hosting');
    }

    /**
     * Soft-delete an Invitation.
     */
    public function destroy(DeleteInvitationRequest $request, Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return to_route('invitations.hosting');
    }

    /**
     * Open a Draft, so its Guests can see it and reply. The send ticket (#907) replaces
     * this with sending.
     */
    public function open(Invitation $invitation): RedirectResponse
    {
        return $this->moveTo($invitation, InvitationState::Open);
    }

    /**
     * Move an Open Invitation back to Draft, which hides it from its Guests. Their Replies
     * stay.
     */
    public function draft(Invitation $invitation): RedirectResponse
    {
        return $this->moveTo($invitation, InvitationState::Draft);
    }

    private function moveTo(Invitation $invitation, InvitationState $state): RedirectResponse
    {
        Gate::authorize('update', $invitation);

        $invitation->state = $state;
        $invitation->save();

        return to_route('invitations.hosting');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function save(Invitation $invitation, array $validated): void
    {
        $invitation->saveWithOptions(Arr::except($validated, 'options'), $validated['options']);
    }
}
