<?php

namespace App\Http\Controllers;

use App\Enums\Reply;
use App\Http\Requests\UpdateReplyRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * A Guest's own Reply (ADR-0034 §10). The Guest is the signed-in Member, never a request
 * field, and the write goes through `Guest::setReply`.
 */
class ReplyController extends Controller
{
    /**
     * Accept with chosen Options, or decline. A Guest may change this until Closed. An
     * accept is refused when the Invitation is full.
     */
    public function update(UpdateReplyRequest $request, Invitation $invitation): RedirectResponse
    {
        $recorded = $invitation->guestFor($request->user())->setReplyWithinLimit(
            $request->enum('reply', Reply::class),
            $request->validated('options', []),
        );

        if (! $recorded) {
            throw ValidationException::withMessages(['reply' => __('invitations.show.full')]);
        }

        return to_route('invitations.show', ['invitation' => $invitation->id]);
    }
}
