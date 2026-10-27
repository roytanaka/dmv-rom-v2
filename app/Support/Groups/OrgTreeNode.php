<?php

namespace App\Support\Groups;

use App\Models\Group;

/** One Group in {@see OrgTree}, with its depth below the top of the tree (the root is 0). */
final class OrgTreeNode
{
    public function __construct(
        public readonly Group $group,
        public readonly int $depth,
    ) {}
}
