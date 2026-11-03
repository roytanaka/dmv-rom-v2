<?php

namespace App\Listeners;

use App\Models\Member;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Writes the Sign-in log (#926, ADR-0038 §12). Runs in the request, so it can read the IP address
 * and user agent. A sign-in is not an edit, so the record's `updated_at` stays put. A Remember me
 * restore counts; impersonation does not.
 */
class RecordSignIn
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        // A support operator becoming a Persona, or coming back, is not a sign-in (ADR-0009). Nor
        // is a Remember me cookie of a Member who has left Current: the next step signs them out.
        if ($this->request->routeIs('impersonation.*')) {
            return;
        }

        /** @var Member $member */
        $member = $event->user;

        if (! $member->isCurrent()) {
            return;
        }

        $now = now();

        $member->signIns()->create([
            'signed_in_at' => $now,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);

        Member::withoutTimestamps(fn () => $member->forceFill(['last_sign_in_at' => $now])->saveQuietly());
    }
}
