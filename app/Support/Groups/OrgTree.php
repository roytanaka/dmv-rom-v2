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
     * Where a Group may sit (#850): every active Group under a section, in tree order, each
     * with its section and its depth below it. A section is an active child of the root: a
     * Container, or a real Group such as Special Projects. The root is never offered as a new
     * parent: a Group always sits under one of the root's children (ADR-0036 §4). For an
     * existing Group, minus the Group and everything under it (no cycles), plus its current
     * parent even when that parent is inactive or the root itself, so the Group can still be
     * saved in place; a root child sees the root as a one-row section, and an inactive section
     * holding the current parent stays, so the form still opens on the current pair.
     *
     * @return list<ParentChoice>
     */
    public function parentChoices(?Group $for = null): array
    {
        $excluded = $for === null ? [] : $this->subtreeIds($for);
        $offered = fn (Group $group) => ! in_array($group->id, $excluded, true)
            && ($group->isActive() || $group->id === $for?->parent_id);

        $choices = [];
        $section = null;
        foreach ($this->nodes as $node) {
            if ($node->depth === 0) {
                if ($node->group->id === $for?->parent_id) {
                    $choices[] = new ParentChoice($node->group, $node->group, 0);
                }

                continue;
            }
            $holdsParent = false;
            if ($node->depth === 1) {
                $holdsParent = $for?->parent_id !== null && in_array($for->parent_id, $this->subtreeIds($node->group), true);
                $section = $offered($node->group) || $holdsParent ? $node : null;
            }
            if ($section !== null && ($offered($node->group) || $holdsParent)) {
                $choices[] = new ParentChoice($node->group, $section->group, $node->depth - $section->depth);
            }
        }

        return $choices;
    }

    /**
     * A parent officer's view of the tree (ADR-0036 §11): each given head with everything below
     * it, archived Groups included, in tree order. Depth counts from the head, so every head
     * sits at 0. A head inside another head's subtree is listed once, as part of the outer one.
     *
     * @param  list<int>  $headIds
     * @return list<OrgTreeNode>
     */
    public function subtrees(array $headIds): array
    {
        $nodes = [];
        $headDepth = null;
        foreach ($this->nodes as $node) {
            if ($headDepth !== null && $node->depth <= $headDepth) {
                $headDepth = null;
            }
            if ($headDepth === null && in_array($node->group->id, $headIds, true)) {
                $headDepth = $node->depth;
            }
            if ($headDepth !== null) {
                $nodes[] = new OrgTreeNode($node->group, $node->depth - $headDepth);
            }
        }

        return $nodes;
    }

    /**
     * Where a parent officer may put a Group (ADR-0036 §11): every active Group in their
     * subtrees, each head included, in tree order. Each choice names its head as its section,
     * with its depth below it. For an existing Group, minus the Group and everything under it,
     * plus its current parent even when that parent is inactive, so it can be saved in place.
     *
     * @param  list<int>  $headIds
     * @return list<ParentChoice>
     */
    public function subtreeParentChoices(array $headIds, ?Group $for = null): array
    {
        $excluded = $for === null ? [] : $this->subtreeIds($for);

        $choices = [];
        $head = null;
        foreach ($this->subtrees($headIds) as $node) {
            $head = $node->depth === 0 ? $node->group : $head;
            if (! in_array($node->group->id, $excluded, true)
                && ($node->group->isActive() || $node->group->id === $for?->parent_id)) {
                $choices[] = new ParentChoice($node->group, $head, $node->depth);
            }
        }

        return $choices;
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
