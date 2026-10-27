<?php

namespace App\Support;

/**
 * How the Feedback tool knows who a Tester is (ADR-0029 §15, ADR-0035 §8 as amended
 * 2026-10-27).
 *
 * On staging and local, logins are shared, so the tool knows a Tester only by what they
 * type. On Beta, each Tester signs in as themselves, so the tool "uses account identity":
 * a Tester reads only their own items, and a Follower's address is the account's email.
 *
 * This is the one predicate for that. It is off until the Beta spec adds the Beta setting
 * (ADR-0035 §6) and wires it to {@see self::CONFIG_KEY}. Tests force it on with
 * `TestCase::forceAccountIdentity()`.
 */
final class FeedbackIdentity
{
    public const CONFIG_KEY = 'feedback.account_identity';

    public static function usesAccountIdentity(): bool
    {
        return (bool) config(self::CONFIG_KEY, false);
    }
}
