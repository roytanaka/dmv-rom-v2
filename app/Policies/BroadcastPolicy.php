<?php

namespace App\Policies;

use App\Models\Broadcast;
use App\Models\Member;
use App\Providers\AppServiceProvider;

/**
 * Who may read a Broadcast sent record (spec #479, ADR-0024 §6). The record is kept in the
 * Records-only field-visibility tier plus the sender for their own sends: Records may audit
 * what the department sent, and an officer may see their own. Super-tier passes through the
 * {@see AppServiceProvider} Gate::before short-circuit and is not re-checked here.
 *
 * No screen reads the record this pass — the policy is the gate a later sent-items screen will
 * lean on, written now so the record is never readable without it.
 */
class BroadcastPolicy
{
    public function view(Member $actor, Broadcast $broadcast): bool
    {
        return $actor->is($broadcast->sender) || $actor->hasMemberAdminAuthority();
    }
}
