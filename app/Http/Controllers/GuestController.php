<?php

namespace App\Http\Controllers;

use App\Enums\AudienceKey;
use App\Http\Requests\StoreGuestAudienceRequest;
use App\Http\Requests\StoreGuestRequest;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use App\Support\Audiences\Audience;
use App\Support\Audiences\AudienceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A Host's guest list (ADR-0034 §3, §11). Only the Host and the super-tier reach it. No
 * row on the page carries an email address.
 */
class GuestController extends Controller
{
    public function __construct(private readonly AudienceResolver $resolver) {}

    /**
     * The guest list, with what the Host may add to it.
     */
    public function index(Request $request, Invitation $invitation): Response
    {
        Gate::authorize('manageGuests', $invitation);

        $guests = $invitation->guests()->with('member')->get();

        return Inertia::render('invitations/Guests', [
            'invitation' => ['id' => $invitation->id, 'name' => $invitation->name],
            'guests' => $guests
                ->sortBy(fn (Guest $guest): string => $guest->member->surnameKey())
                ->map(fn (Guest $guest): array => ['id' => $guest->id, 'name' => $guest->member->fullName()])
                ->values()->all(),
            'candidates' => $invitation->candidates(),
            'audiences' => $this->resolver->guestAudiences($request->user())
                ->map(fn (Audience $audience): array => [
                    'key' => $audience->key->value,
                    'parameter' => $audience->parameter,
                    'label' => $audience->label,
                ])->all(),
        ]);
    }

    /**
     * Add one current Member.
     */
    public function store(StoreGuestRequest $request, Invitation $invitation): RedirectResponse
    {
        $invitation->addGuests(Member::query()->whereKey($request->integer('member'))->get());

        return $this->toList($invitation);
    }

    /**
     * Add a whole Audience, resolved on the server.
     */
    public function storeAudience(StoreGuestAudienceRequest $request, Invitation $invitation): RedirectResponse
    {
        $invitation->addGuests($this->resolver->resolveGuests(
            $request->user(),
            $request->enum('audience', AudienceKey::class),
            $request->input('parameter'),
        ));

        return $this->toList($invitation);
    }

    /**
     * Remove a Guest. The Reply lives on the row, so it goes too, and no mail is sent
     * (ADR-0034 §8).
     */
    public function destroy(Invitation $invitation, Guest $guest): RedirectResponse
    {
        Gate::authorize('manageGuests', $invitation);

        $guest->delete();

        return $this->toList($invitation);
    }

    private function toList(Invitation $invitation): RedirectResponse
    {
        return to_route('invitations.guests', ['invitation' => $invitation->id]);
    }
}
