<?php

namespace App\Support\Groups;

use App\Models\Group;

/**
 * One place a Group may sit (#850): the Group, the section whose subtree it is in (a child
 * of the root: a Container or a real Group such as Special Projects), and its depth below
 * that section (the section itself is 0). The picker lists the sections first, then the
 * chosen section's subtree.
 */
final class ParentChoice
{
    public function __construct(
        public readonly Group $group,
        public readonly Group $section,
        public readonly int $depth,
    ) {}
}
