<?php

namespace App\Support;

/**
 * The deployed app version (#673, ADR-0029 §11): the short commit id and the deploy
 * instant (UTC), read from the version.json that scripts/deploy.sh writes. Null in local
 * development, where no deploy wrote the file.
 *
 * The footer shows it on every page, and each Feedback item stores it (#676).
 */
final class AppVersion
{
    /**
     * @return array{commit: string, deployedAt: string}|null
     */
    public static function current(): ?array
    {
        $version = config('app.version');

        if (! isset($version['commit'], $version['deployed_at'])) {
            return null;
        }

        return [
            'commit' => substr($version['commit'], 0, 7),
            'deployedAt' => $version['deployed_at'],
        ];
    }

    /** The version as one line, "abc1234 2026-09-26T23:14:24Z", or null. */
    public static function label(): ?string
    {
        $version = self::current();

        return $version === null ? null : "{$version['commit']} {$version['deployedAt']}";
    }
}
