<?php

namespace App\Support\Groups;

use App\Models\Group;

/**
 * The whole org tree in the rail's order, loaded in one query (ADR-0036 §3) plus one for every
 * Group's stewardships (#848): each parentless Group, then its children depth-first, siblings by
 * `display_order` then name. Archived and expired Groups stay in place. DMV Settings reads its
 * tree, its parent choices and its stewardship choices from here.
 */
final class OrgTree
{
    /**
     * @param  list<OrgTreeNode>  $nodes
     */
    private function __construct(public readonly array $nodes) {}

    public static function load(): self
    {
        $byParent = Group::query()->with('stewardships')->get()->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $nodes = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$nodes, $byParent): void {
            $siblings = ($byParent->get($parentId) ?? collect())
                ->sortBy([['display_order', 'asc'], ['name', 'asc']]);

            foreach ($siblings as $group) {
                $nodes[] = new OrgTreeNode($group, $depth);
                $walk($group->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return new self($nodes);
    }

    /**
     * Where a Group may sit: every active Group, the root included. For an existing Group,
     * minus the Group and everything under it (no cycles), plus its current parent even when
     * that parent is inactive, so the Group can still be saved in place.
     *
     * @return list<OrgTreeNode>
     */
    public function parentChoices(?Group $for = null): array
    {
        $excluded = $for === null ? [] : $this->subtreeIds($for);

        return array_values(array_filter(
            $this->nodes,
            fn (OrgTreeNode $node) => ! in_array($node->group->id, $excluded, true)
                && ($node->group->isActive() || $node->group->id === $for?->parent_id),
        ));
    }

    /**
     * The Groups a stewardship may go to: active ones, the root left out (it holds nothing;
     * ADR-0036 §7).
     *
     * @return list<OrgTreeNode>
     */
    public function stewardshipChoices(): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn (OrgTreeNode $node) => ! $node->group->isRoot() && $node->group->isActive(),
        ));
    }

    /**
     * The Group's id and every id beneath it. Tree order puts a subtree right after its head,
     * deeper than it, so the walk stops at the next node back at the head's depth or above.
     *
     * @return list<int>
     */
    private function subtreeIds(Group $head): array
    {
        $ids = [];
        $depth = null;
        foreach ($this->nodes as $node) {
            if ($depth === null) {
                if ($node->group->id === $head->id) {
                    $depth = $node->depth;
                    $ids[] = $head->id;
                }

                continue;
            }
            if ($node->depth <= $depth) {
                break;
            }
            $ids[] = $node->group->id;
        }

        return $ids === [] ? [$head->id] : $ids;
    }
}
