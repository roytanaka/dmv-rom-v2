<?php

namespace App\Enums;

/**
 * A Shift's audience (ADR-0021 §4) — who sees a Sign-up button on it. This is the
 * discovery filter, distinct from the two *floors* (may this person work at all); the
 * floors answer "may they", the audience answers "who is shown the offer".
 *
 * - `group` (default) — Members of the owning Group. The permissive case is always a
 *   deliberate choice, so the default is the narrow one, not legacy's `everyone`.
 * - `open` — any Member who can see the Schedule, so a Scheduler can invite the whole
 *   org to a Shift they cannot staff internally.
 *
 * Four of legacy's five audience values are dropped as workarounds (nobody, the
 * hardcoded "MIS" union, a committee id, a subcommittee id + 100). Two cases are chosen
 * so the enum can widen to a Group-list later without reshaping Sign-up.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum ShiftAudience: string
{
    case Group = 'group';
    case Open = 'open';
}
