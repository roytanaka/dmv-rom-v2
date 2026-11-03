<?php

namespace App\Listeners;

use App\Models\Member;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Writes the Sign-in log (#926, ADR-0038 §12). Runs in the request, so it can read the IP address
 * and user agent. A sign-in is not an edit, so the record's `updated_at` stays put.
 */
class RecordSignIn
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        /** @var Member $member */
        $member = $event->user;
        $now = now();

        $member->signIns()->create([
            'signed_in_at' => $now,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);

        Member::withoutTimestamps(fn () => $member->forceFill(['last_sign_in_at' => $now])->saveQuietly());
    }
}
