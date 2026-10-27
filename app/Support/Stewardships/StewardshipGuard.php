<?php

namespace App\Support\Stewardships;

use App\Models\Group;
use App\Models\GroupStewardship;
use App\Support\NameList;

/**
 * A stewardship holder must stay active and editable (ADR-0036 §2): archiving it, making it a
 * Container or ending it would strand the authority it carries. Each of those writes refuses
 * with the same copy, naming every stewardship the Group holds.
 */
final class StewardshipGuard
{
    /** The refusal for a Group that holds a stewardship; null when it holds none. */
    public static function refusal(Group $group): ?string
    {
        $held = $group->stewardships()->get();
        if ($held->isEmpty()) {
            return null;
        }

        return __('dmv_settings.error.archive_steward', [
            'group' => $group->name,
            'stewardship' => NameList::join(
                $held->map(fn (GroupStewardship $row) => __("dmv_settings.stewardship.{$row->function->value}.title")),
            ),
        ]);
    }
}
