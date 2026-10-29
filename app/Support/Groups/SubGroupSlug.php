<?php

namespace App\Support\Groups;

use App\Models\Group;
use Illuminate\Support\Str;

/**
 * The slug of a parent officer's new sub-group (#884, ADR-0036 §11). They never see or set it.
 * From the name in the slug format (lowercase letters, digits, single hyphens). If another
 * Group uses it, the parent's slug goes in front (`docents-sections`); if that is taken too,
 * `-2`, `-3` and so on follow.
 */
final class SubGroupSlug
{
    private const MAX = 255;

    public static function for(string $name, Group $parent): string
    {
        $base = Str::slug($name);
        if ($base !== '' && ! self::taken($base)) {
            return $base;
        }

        $prefixed = self::fit(trim($parent->slug.'-'.$base, '-'), 0);
        if (! self::taken($prefixed)) {
            return $prefixed;
        }

        for ($n = 2; ; $n++) {
            $candidate = self::fit($prefixed, strlen("-{$n}")).'-'.$n;
            if (! self::taken($candidate)) {
                return $candidate;
            }
        }
    }

    private static function taken(string $slug): bool
    {
        return Group::query()->where('slug', $slug)->exists();
    }

    /** Cut $slug so $reserve more characters still fit the column, no hyphen left dangling. */
    private static function fit(string $slug, int $reserve): string
    {
        return rtrim(substr($slug, 0, self::MAX - $reserve), '-');
    }
}
