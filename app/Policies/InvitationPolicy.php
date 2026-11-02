<?php

namespace App\Policies;

use App\Enums\AccessTier;
use App\Enums\InvitationState;
use App\Models\Invitation;
use App\Models\Member;

/**
 * Authorization for Invitations (ADR-0034). Any Member whose Category gives Full
 * access may host; a Host manages only their own Invitations, and only while they
 * still have Full access.
 *
 * The super-tier short-circuit lives in a single `Gate::before` (AppServiceProvider)
 * and is never re-checked here, so every method below reasons only about the
 * non-super-tier cases.
 */
class InvitationPolicy
{
    /**
     * Who may host: this opens the Hosting tab and creates an Invitation.
     */
    public function create(Member $actor): bool
    {
        return $actor->category->accessTier() === AccessTier::Full;
    }

    /**
     * Who may read a single Invitation: its Host, and a Guest while it is not a Draft.
     * Every other Member is refused, so a private occasion stays private.
     */
    public function view(Member $actor, Invitation $invitation): bool
    {
        return $this->manages($actor, $invitation)
            || ($invitation->state !== InvitationState::Draft && $invitation->guestFor($actor) !== null);
    }

    /**
     * Who may edit an Invitation: its Host.
     */
    public function update(Member $actor, Invitation $invitation): bool
    {
        return $this->manages($actor, $invitation);
    }

    /**
     * Who may delete an Invitation: its Host.
     */
    public function delete(Member $actor, Invitation $invitation): bool
    {
        return $this->manages($actor, $invitation);
    }

    /**
     * Who may see and change the guest list: its Host. The Guest row holds the Reply,
     * so this also guards the Replies (ADR-0034 §11).
     */
    public function manageGuests(Member $actor, Invitation $invitation): bool
    {
        return $this->manages($actor, $invitation);
    }

    /**
     * The shared predicate: the actor is the Host and may still host.
     */
    private function manages(Member $actor, Invitation $invitation): bool
    {
        return $invitation->host_id === $actor->id && $this->create($actor);
    }
}
